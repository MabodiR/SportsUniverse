<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\Video;
use Illuminate\Support\Facades\Redis;
use Throwable;

class BufferedVideoCounters
{
    private const HASH = 'video:counters:views';
    private const DIRTY = 'video:counters:dirty';

    public function enabled(): bool { return (bool) config('scale.redis_counters'); }

    public function increment(Video $video, int $amount = 1): int
    {
        if (! $this->enabled()) {
            $video->increment('views_count', $amount);
            return (int) $video->fresh()->views_count;
        }
        Redis::connection()->hincrby(self::HASH, (string) $video->id, $amount);
        Redis::connection()->sadd(self::DIRTY, (string) $video->id);
        return (int) $video->views_count + $this->pending($video->id);
    }

    public function pending(int $videoId): int
    {
        if (! $this->enabled()) return 0;
        return (int) (Redis::connection()->hget(self::HASH, (string) $videoId) ?: 0);
    }

    public function flush(int $limit = 1000): int
    {
        if (! $this->enabled()) return 0;
        $ids = Redis::connection()->spop(self::DIRTY, $limit);
        $ids = is_array($ids) ? $ids : array_filter([$ids]);
        $flushed = 0;
        foreach ($ids as $id) {
            $amount = (int) Redis::connection()->eval(
                "local value=redis.call('HGET',KEYS[1],ARGV[1]); if value then redis.call('HDEL',KEYS[1],ARGV[1]); return value else return 0 end",
                1, self::HASH, (string) $id
            );
            if ($amount < 1) continue;
            try {
                Video::whereKey((int) $id)->increment('views_count', $amount);
                $flushed++;
            } catch (Throwable $exception) {
                Redis::connection()->hincrby(self::HASH, (string) $id, $amount);
                Redis::connection()->sadd(self::DIRTY, (string) $id);
                throw $exception;
            }
        }
        return $flushed;
    }
}
