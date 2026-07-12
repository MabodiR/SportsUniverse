<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Domain\Messaging\Models\Conversation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Messaging\ConversationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $page = $request->user()->belongsToMany(Conversation::class, 'conversation_participants')->wherePivotNull('archived_at')->with('participants.profile', 'latestMessage.sender.profile', 'latestMessage.media')->orderByDesc('last_message_at')->paginate(20);
        foreach ($page as $conversation) {
            $lastRead = $conversation->participants->firstWhere('id', $request->user()->id)?->pivot?->last_read_at;
            $conversation->unread_count = $conversation->messages()->where('sender_id', '!=', $request->user()->id)->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))->count();
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
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return response()->json(['message' => 'Conversation marked as read.']);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['archived_at' => now()]);

        return response()->json(['message' => 'Conversation archived.']);
    }
}
