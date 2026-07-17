<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendExpoPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId, public string $category, public array $payload) {}

    public function handle(): void
    {
        $subscriptions = DB::table('push_subscriptions')->where('user_id', $this->userId)->where('provider', 'expo')->get();
        if ($subscriptions->isEmpty()) return;
        $messages = $subscriptions->map(fn ($subscription) => ['to' => $subscription->endpoint, 'sound' => 'default', 'title' => $this->title(), 'body' => $this->body(), 'data' => ['category' => $this->category, ...$this->payload], 'channelId' => 'default'])->values()->all();
        $response = Http::timeout(12)->retry(2, 300)->post('https://exp.host/--/api/v2/push/send', $messages);
        if (! $response->successful()) $response->throw();
        foreach ((array) $response->json('data', []) as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($subscriptions[$index])) DB::table('push_subscriptions')->where('id', $subscriptions[$index]->id)->delete();
        }
    }

    private function title(): string
    {
        return $this->payload['title'] ?? match ($this->category) {
            'messages' => 'New message', 'message_requests' => 'New message request', 'opportunities' => 'Opportunity update',
            'followers' => 'New follower', 'engagement' => 'New post activity', 'moderation' => 'Safety update', default => 'SportUniverse',
        };
    }

    private function body(): string
    {
        return $this->payload['message'] ?? $this->payload['preview'] ?? $this->payload['opportunity_title'] ?? match ($this->payload['event'] ?? '') {
            'video_liked' => ($this->payload['actor_name'] ?? 'Someone').' liked your post.',
            'video_commented' => ($this->payload['actor_name'] ?? 'Someone').' commented on your post.',
            'new_follower' => ($this->payload['actor_name'] ?? 'Someone').' followed you.',
            'trial_invitation' => 'You received a trial invitation from '.($this->payload['club_name'] ?? 'a club').'.',
            'opportunity_application_status' => 'Your application status was updated.',
            default => 'You have new activity on SportUniverse.',
        };
    }
}
