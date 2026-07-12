<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreMediaUpload
{
    public function execute(User $user, UploadedFile $file, string $kind, string $collection = 'uploads'): Media
    {
        $disk = (string) config('media.disk');
        $publicId = (string) Str::ulid();
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = "users/{$user->id}/{$kind}/{$publicId}.{$extension}";
        try {
            $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private']);
            if (! $stored) {
                throw new \RuntimeException('The upload could not be stored.');
            }$media = Media::create(['public_id' => $publicId, 'user_id' => $user->id, 'kind' => $kind, 'collection' => $collection, 'disk' => $disk, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath()), 'processing_status' => 'pending', 'moderation_status' => config('media.requires_moderation') ? 'pending' : 'approved']);
            ProcessMedia::dispatch($media)->afterCommit();

            return $media;
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }
}
