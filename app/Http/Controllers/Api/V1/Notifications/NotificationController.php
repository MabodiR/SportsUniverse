<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\Notifications\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->notifications()->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))->when($request->filled('category'), fn ($q) => $q->where('data->category', $request->string('category')));

        return NotificationResource::collection($query->paginate(min($request->integer('per_page', 30), 100)));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return response()->json(['data' => (new NotificationResource($item))->resolve($request)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.', 'data' => ['updated' => $count]]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }

    public function preferences(Request $request): JsonResponse
    {
        $preferences = $request->user()->notificationPreference()->firstOrCreate([]);

        return response()->json(['data' => $preferences->only(['messages', 'message_requests', 'opportunities', 'followers', 'engagement', 'moderation', 'profile_views', 'email_digest'])]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $preferences = $request->user()->notificationPreference()->updateOrCreate([], $request->validated());

        return response()->json(['message' => 'Notification preferences updated.', 'data' => $preferences->only(['messages', 'message_requests', 'opportunities', 'followers', 'engagement', 'moderation', 'profile_views', 'email_digest'])]);
    }
}
