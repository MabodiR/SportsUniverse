<?php

return [
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
    'requires_moderation' => (bool) env('MEDIA_REQUIRES_MODERATION', false),
    'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),
    'max_image_kb' => (int) env('MEDIA_MAX_IMAGE_KB', 10240),
    'max_video_kb' => (int) env('MEDIA_MAX_VIDEO_KB', 512000),
    'max_document_kb' => (int) env('MEDIA_MAX_DOCUMENT_KB', 20480),
];
