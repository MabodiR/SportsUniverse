<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Live\Events\LiveActivity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiveStreamController extends Controller
{
    public function index(): JsonResponse
    {
        $this->expireStaleStreams();

        return response()->json(['data' => DB::table('live_streams')->join('users', 'users.id', '=', 'live_streams.user_id')->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')->where('live_streams.status', 'live')->latest('live_streams.started_at')->get(['live_streams.public_id as id', 'live_streams.user_id as host_id', 'live_streams.title', 'live_streams.description', 'live_streams.viewer_count', 'live_streams.started_at', 'users.name as host_name', 'user_profiles.slug', 'user_profiles.profile_image_path as image'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:1000']]);
        DB::table('live_streams')->where('user_id', $request->user()->id)->where('status', 'live')->update(['status' => 'ended', 'ended_at' => now(), 'updated_at' => now()]);
        $id = (string) Str::ulid();
        DB::table('live_streams')->insert(['public_id' => $id, 'user_id' => $request->user()->id, ...$data, 'status' => 'live', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function show(string $stream): JsonResponse
    {
        $this->expireStaleStreams();
        $item = DB::table('live_streams')->join('users', 'users.id', '=', 'live_streams.user_id')->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')->where('live_streams.public_id', $stream)->first(['live_streams.*', 'live_streams.user_id as host_id', 'users.name as host_name', 'user_profiles.slug', 'user_profiles.profile_image_path as image']);
        abort_unless($item, 404);
        $messages = DB::table('live_messages')->join('users', 'users.id', '=', 'live_messages.user_id')->where('live_stream_id', $item->id)->latest('live_messages.id')->limit(60)->get(['live_messages.id', 'live_messages.body', 'live_messages.reaction', 'live_messages.created_at', 'users.name'])->reverse()->values();

        return response()->json(['data' => ['stream' => $item, 'messages' => $messages]]);
    }

    public function join(Request $request, string $stream): JsonResponse
    {
        $count = DB::transaction(function () use ($stream) {
            $item = DB::table('live_streams')->where('public_id', $stream)->where('status', 'live')->lockForUpdate()->first();
            abort_unless($item, 404);
            $count = $item->viewer_count + 1;
            DB::table('live_streams')->where('id', $item->id)->update(['viewer_count' => $count, 'peak_viewers' => max($item->peak_viewers, $count)]);

            return $count;
        });
        broadcast(new LiveActivity($stream, ['type' => 'viewer_join', 'count' => $count, 'viewer_id' => $request->user()->id]));

        return response()->json(['data' => ['viewer_count' => $count]]);
    }

    public function message(Request $request, string $stream): JsonResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:300'], 'reaction' => ['nullable', 'in:heart,fire,clap,football']]);
        abort_if(empty($data['body']) && empty($data['reaction']), 422);
        $live = DB::table('live_streams')->where('public_id', $stream)->where('status', 'live')->first();
        abort_unless($live, 404);
        $id = DB::table('live_messages')->insertGetId(['live_stream_id' => $live->id, 'user_id' => $request->user()->id, 'body' => $data['body'] ?? null, 'reaction' => $data['reaction'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        $activity = ['type' => ! empty($data['reaction']) ? 'reaction' : 'message', 'id' => $id, 'name' => $request->user()->name, ...$data, 'created_at' => now()->toISOString()];
        broadcast(new LiveActivity($stream, $activity));

        return response()->json(['data' => $activity], 201);
    }

    public function end(Request $request, string $stream): JsonResponse
    {
        $owned = DB::table('live_streams')->where('public_id', $stream)->where('user_id', $request->user()->id)->exists();
        abort_unless($owned, 403);
        $updated = DB::table('live_streams')->where('public_id', $stream)->where('user_id', $request->user()->id)->where('status', 'live')->update(['status' => 'ended', 'ended_at' => now(), 'updated_at' => now()]);
        if ($updated) {
            broadcast(new LiveActivity($stream, ['type' => 'ended']));
        }

        return response()->json(['message' => 'Live ended.']);
    }

    public function heartbeat(Request $request, string $stream): JsonResponse
    {
        $updated = DB::table('live_streams')->where('public_id', $stream)->where('user_id', $request->user()->id)->where('status', 'live')->update(['updated_at' => now()]);
        abort_unless($updated, 404);

        return response()->json(['message' => 'Live heartbeat received.']);
    }

    public function signal(Request $request, string $stream): JsonResponse
    {
        $data = $request->validate(['target_id' => ['required', 'integer', 'exists:users,id'], 'kind' => ['required', 'in:offer,answer,ice'], 'payload' => ['required', 'array']]);
        abort_unless(DB::table('live_streams')->where('public_id', $stream)->where('status', 'live')->exists(), 404);
        broadcast(new LiveActivity($stream, ['type' => 'signal', 'sender_id' => $request->user()->id, ...$data]));

        return response()->json(['message' => 'Signal sent.']);
    }

    private function expireStaleStreams(): void
    {
        DB::table('live_streams')
            ->where('status', 'live')
            ->where('updated_at', '<', now()->subSeconds(45))
            ->update(['status' => 'ended', 'ended_at' => now(), 'updated_at' => now()]);
    }
}
