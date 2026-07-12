<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleVideoEngagement
{
    public function execute(User $user, Video $video, string $type): bool
    {
        return DB::transaction(function () use ($user, $video, $type) {
            [$table,$counter] = match ($type) {
                'like' => ['video_likes', 'likes_count'],'save' => ['saved_videos', 'saves_count']
            };
            $locked = Video::lockForUpdate()->findOrFail($video->id);
            $query = DB::table($table)->where('video_id', $video->id)->where('user_id', $user->id);
            if ($query->exists()) {
                $query->delete();
                $locked->update([$counter => max(0, $locked->{$counter} - 1)]);

                return false;
            }DB::table($table)->insert(['video_id' => $video->id, 'user_id' => $user->id, 'created_at' => now()]);
            $locked->increment($counter);

            return true;
        });
    }
}
