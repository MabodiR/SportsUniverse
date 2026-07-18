<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Messaging\Events\MessageSent;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Messaging\Models\MessageRequest;
use App\Models\User;
use App\Domain\Media\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_acceptance_creates_direct_conversation_and_initial_message(): void
    {
        [$sender,$recipient] = $this->users();
        $created = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/message-requests', ['recipient_id' => $recipient->id, 'message' => 'Can we discuss your academy?'])->assertCreated();
        $requestId = $created->json('data.id');
        $accepted = $this->actingAs($recipient, 'sanctum')->postJson('/api/v1/message-requests/'.$requestId.'/accept')->assertOk()->assertJsonPath('data.status', 'accepted');
        $conversationId = $accepted->json('data.conversation_id');
        $this->assertNotNull($conversationId);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
        $this->assertDatabaseHas('messages', ['body' => 'Can we discuss your academy?']);
    }

    public function test_participant_can_send_message_and_recipient_is_notified(): void
    {
        [$sender,$recipient] = $this->users();
        $conversation = $this->conversation($sender, $recipient);
        Event::fake([MessageSent::class]);
        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/conversations/'.$conversation->public_id.'/messages', ['body' => 'Training starts at 17:00'])->assertCreated()->assertJsonPath('data.body', 'Training starts at 17:00');
        Event::assertDispatched(MessageSent::class);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $recipient->id]);
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    public function test_sender_can_edit_an_unseen_text_message_but_not_after_it_is_read(): void
    {
        [$sender, $recipient] = $this->users();
        $conversation = $this->conversation($sender, $recipient);
        $message = Message::create(['public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'body' => 'Original']);

        $this->actingAs($sender, 'sanctum')->patchJson('/api/v1/conversations/'.$conversation->public_id.'/messages/'.$message->public_id, ['body' => 'Corrected'])
            ->assertOk()->assertJsonPath('data.body', 'Corrected');

        $conversation->participants()->updateExistingPivot($recipient->id, ['last_read_at' => now()]);
        $this->patchJson('/api/v1/conversations/'.$conversation->public_id.'/messages/'.$message->public_id, ['body' => 'Too late'])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_sender_can_delete_their_message_but_recipient_cannot(): void
    {
        [$sender, $recipient] = $this->users();
        $conversation = $this->conversation($sender, $recipient);
        $message = Message::create(['public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'body' => 'Remove me']);

        $this->actingAs($recipient, 'sanctum')->deleteJson('/api/v1/conversations/'.$conversation->public_id.'/messages/'.$message->public_id)->assertForbidden();
        $this->actingAs($sender, 'sanctum')->deleteJson('/api/v1/conversations/'.$conversation->public_id.'/messages/'.$message->public_id)
            ->assertOk()->assertJsonPath('data.body', null);
        $this->assertNotNull($message->fresh()->deleted_at);
    }

    public function test_outsider_cannot_read_conversation(): void
    {
        [$sender,$recipient] = $this->users();
        $outsider = User::factory()->create();
        $conversation = $this->conversation($sender, $recipient);
        $this->actingAs($outsider, 'sanctum')->getJson('/api/v1/conversations/'.$conversation->public_id.'/messages')->assertForbidden();
    }

    public function test_recipient_can_decline_request(): void
    {
        [$sender,$recipient] = $this->users();
        $request = MessageRequest::create(['public_id' => (string) Str::ulid(), 'sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'message' => 'Hello', 'status' => 'pending']);
        $this->actingAs($recipient, 'sanctum')->postJson('/api/v1/message-requests/'.$request->public_id.'/decline')->assertOk()->assertJsonPath('data.status', 'declined');
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_block_prevents_new_message_requests(): void
    {
        [$sender,$recipient] = $this->users();
        $this->actingAs($recipient, 'sanctum')->postJson('/api/v1/profiles/'.$sender->id.'/block')->assertOk();
        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/message-requests', ['recipient_id' => $recipient->id, 'message' => 'Hello'])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
    }

    public function test_user_can_list_and_unblock_blocked_users(): void
    {
        [$viewer, $blocked] = $this->users();
        $blocked->profile()->create(['slug' => 'blocked-member']);
        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/profiles/'.$blocked->id.'/block')->assertOk();
        $this->getJson('/api/v1/blocked-users')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $blocked->id);
        $this->deleteJson('/api/v1/profiles/'.$blocked->id.'/block')->assertOk();
        $this->getJson('/api/v1/blocked-users')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_participant_can_send_picture_and_video_messages(): void
    {
        [$sender,$recipient]=$this->users();$conversation=$this->conversation($sender,$recipient);
        foreach(['image','video'] as $kind){$media=Media::factory()->for($sender)->create(['kind'=>$kind]);$this->actingAs($sender,'sanctum')->postJson('/api/v1/conversations/'.$conversation->public_id.'/messages',['media_id'=>$media->public_id])->assertCreated()->assertJsonPath('data.media.kind',$kind);}
    }

    public function test_participant_can_mute_archive_report_and_mark_read(): void
    {
        [$sender,$recipient]=$this->users();$conversation=$this->conversation($sender,$recipient);
        $this->actingAs($sender,'sanctum')->postJson('/api/v1/conversations/'.$conversation->public_id.'/mute',['muted'=>true])->assertOk()->assertJsonPath('data.muted',true);
        $this->postJson('/api/v1/conversations/'.$conversation->public_id.'/read')->assertOk();
        $this->postJson('/api/v1/conversations/'.$conversation->public_id.'/report',['reason'=>'spam'])->assertCreated();
        $this->postJson('/api/v1/conversations/'.$conversation->public_id.'/archive')->assertOk();
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/conversations?archived=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.archived', true)->assertJsonPath('data.0.reported', true);
        $this->getJson('/api/v1/profiles/'.$recipient->id.'/messaging-context')->assertOk()->assertJsonPath('data.conversation.archived', true);
        $this->postJson('/api/v1/profiles/'.$recipient->id.'/block', ['reason' => 'Safety'])->assertOk();
        $this->getJson('/api/v1/conversations?archived=1')->assertOk()->assertJsonPath('data.0.blocked', true);
        $this->deleteJson('/api/v1/conversations/'.$conversation->public_id.'/archive')->assertOk();
        $this->assertDatabaseHas('conversation_participants',['conversation_id'=>$conversation->id,'user_id'=>$sender->id]);
        $this->assertDatabaseHas('reports',['reportable_type'=>$conversation->getMorphClass(),'reportable_id'=>$conversation->id]);
    }

    private function users(): array
    {
        return [User::factory()->create(), User::factory()->create()];
    }

    private function conversation(User $one, User $two): Conversation
    {
        $conversation = Conversation::create(['public_id' => (string) Str::ulid(), 'type' => 'direct', 'direct_key' => collect([$one->id, $two->id])->sort()->join(':')]);
        $conversation->participants()->attach([$one->id => ['joined_at' => now()], $two->id => ['joined_at' => now()]]);

        return $conversation;
    }
}
