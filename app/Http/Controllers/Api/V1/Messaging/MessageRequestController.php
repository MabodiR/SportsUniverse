<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Domain\Messaging\Actions\CreateMessageRequest;
use App\Domain\Messaging\Actions\RespondToMessageRequest;
use App\Domain\Messaging\Models\MessageRequest;
use App\Events\NotificationRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Messaging\StoreMessageRequestRequest;
use App\Http\Resources\Api\V1\Messaging\MessageRequestResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $direction = $request->string('direction', 'incoming')->value();
        $query = MessageRequest::query()->when($direction === 'outgoing', fn ($q) => $q->where('sender_id', $request->user()->id), fn ($q) => $q->where('recipient_id', $request->user()->id))->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->with('sender.profile', 'recipient.profile', 'conversation')->latest();

        return MessageRequestResource::collection($query->paginate(20));
    }

    public function store(StoreMessageRequestRequest $request, CreateMessageRequest $create): JsonResponse
    {
        $recipient = User::findOrFail($request->integer('recipient_id'));
        $messageRequest = $create->execute($request->user(), $recipient, $request->validated('message'));
        NotificationRequested::dispatch($recipient->id, 'message_requests', ['event' => 'message_request_received', 'request_id' => $messageRequest->public_id, 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name]);

        return response()->json(['message' => 'Message request sent.', 'data' => new MessageRequestResource($messageRequest->load('sender.profile', 'recipient.profile', 'conversation'))], 201);
    }

    public function accept(MessageRequest $messageRequest, RespondToMessageRequest $respond): JsonResponse
    {
        Gate::authorize('respond', $messageRequest);
        $result = $respond->execute($messageRequest, true);
        NotificationRequested::dispatch($result->sender_id, 'message_requests', ['event' => 'message_request_accepted', 'request_id' => $result->public_id, 'conversation_id' => $result->conversation->public_id, 'actor_id' => $result->recipient_id, 'actor_name' => $result->recipient->name]);

        return response()->json(['message' => 'Message request accepted.', 'data' => new MessageRequestResource($result->load('sender.profile', 'recipient.profile', 'conversation'))]);
    }

    public function decline(MessageRequest $messageRequest, RespondToMessageRequest $respond): JsonResponse
    {
        Gate::authorize('respond', $messageRequest);
        $result = $respond->execute($messageRequest, false);

        return response()->json(['message' => 'Message request declined.', 'data' => new MessageRequestResource($result->load('sender.profile', 'recipient.profile', 'conversation'))]);
    }
}
