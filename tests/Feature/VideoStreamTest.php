<?php

namespace Tests\Feature;

use App\Domain\Feed\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_video_stream_supports_browser_byte_ranges(): void
    {
        Storage::fake('local');
        $video = Video::factory()->create();
        Storage::disk('local')->put($video->media->path, '0123456789');

        $this->withHeader('Range', 'bytes=0-3')
            ->get(route('videos.stream', $video))
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-3/10');
    }
}
