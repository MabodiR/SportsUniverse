<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Events\ConversationRead;
use App\Domain\Messaging\Events\TypingUpdated;
use App\Domain\Moderation\Models\Report;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Messaging\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->belongsToMany(Conversation::class, 'conversation_participants');
        $request->boolean('archived') ? $query->wherePivotNotNull('archived_at') : $query->wherePivotNull('archived_at');
        $page = $query->with('participants.profile', 'latestMessage.sender.profile', 'latestMessage.media')->orderByDesc('last_message_at')->paginate(20);
        $conversationIds = $page->getCollection()->pluck('id');
        $reported = Report::where('reporter_id', $request->user()->id)->where('reportable_type', (new Conversation)->getMorphClass())->whereIn('reportable_id', $conversationIds)->pluck('reportable_id')->flip();
        $participantIds = $page->getCollection()->flatMap(fn ($conversation) => $conversation->participants->pluck('id'))->unique();
        $blocked = DB::table('user_blocks')->where('blocker_id', $request->user()->id)->whereIn('blocked_id', $participantIds)->pluck('blocked_id')->flip();
        foreach ($page as $conversation) {
            $lastRead = $conversation->participants->firstWhere('id', $request->user()->id)?->pivot?->last_read_at;
            $conversation->unread_count = $conversation->messages()->where('sender_id', '!=', $request->user()->id)->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))->count();
            $conversation->reported_by_viewer = $reported->has($conversation->id);
            $conversation->blocked_by_viewer = $conversation->participants->where('id', '!=', $request->user()->id)->contains(fn ($participant) => $blocked->has($participant->id));
        }

return ConversationResource::collection($page);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        return new ConversationResource($conversation->load('participants.profile', 'latestMessage.sender.profile', 'latestMessage.media'));
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $readAt=now();$conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => $readAt]);
        ConversationRead::dispatch($conversation->public_id,$request->user()->id,$readAt->toAtomString());

        return response()->json(['message' => 'Conversation marked as read.']);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['archived_at' => now()]);

        return response()->json(['message' => 'Conversation archived.']);
    }

    public function unarchive(Request $request,Conversation $conversation):JsonResponse {Gate::authorize('view',$conversation);$conversation->participants()->updateExistingPivot($request->user()->id,['archived_at'=>null]);return response()->json(['message'=>'Conversation restored.']);}
    public function mute(Request $request,Conversation $conversation):JsonResponse {Gate::authorize('view',$conversation);$muted=$request->boolean('muted',true);$conversation->participants()->updateExistingPivot($request->user()->id,['muted_at'=>$muted?now():null]);return response()->json(['data'=>['muted'=>$muted]]);}
    public function typing(Request $request,Conversation $conversation):JsonResponse {Gate::authorize('view',$conversation);$data=$request->validate(['typing'=>['required','boolean']]);TypingUpdated::dispatch($conversation->public_id,$request->user()->id,$request->user()->name,(bool)$data['typing']);return response()->json([],204);}
    public function report(Request $request,Conversation $conversation):JsonResponse {Gate::authorize('view',$conversation);$data=$request->validate(['reason'=>['required','in:spam,harassment,hate,nudity,violence,fraud,impersonation,copyright,other'],'details'=>['nullable','string','max:5000']]);$report=Report::create(['public_id'=>(string)Str::ulid(),'reporter_id'=>$request->user()->id,'reportable_type'=>$conversation->getMorphClass(),'reportable_id'=>$conversation->id,'reason'=>$data['reason'],'details'=>$data['details']??null,'status'=>'open']);return response()->json(['data'=>['id'=>$report->public_id,'status'=>'open']],201);}
}
