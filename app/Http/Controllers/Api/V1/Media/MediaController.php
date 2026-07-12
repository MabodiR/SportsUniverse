<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Domain\Media\Actions\StoreMediaUpload;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Media\StoreMediaRequest;
use App\Http\Resources\Api\V1\Media\MediaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->media()->latest();
        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }if ($request->filled('collection')) {
            $query->where('collection', $request->string('collection'));
        }

return MediaResource::collection($query->paginate(min($request->integer('per_page', 20), 50)));
    }

    public function store(StoreMediaRequest $request, StoreMediaUpload $store): JsonResponse
    {
        $media = $store->execute($request->user(), $request->file('file'), $request->validated('kind'), $request->validated('collection', 'uploads'));

        return response()->json(['message' => 'Upload received and queued for processing.', 'data' => new MediaResource($media)], 202);
    }

    public function show(Media $media): MediaResource
    {
        Gate::authorize('view', $media);

        return new MediaResource($media);
    }

    public function download(Media $media): StreamedResponse
    {
        Gate::authorize('view', $media);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path, $media->original_name, ['Content-Type' => $media->mime_type]);
    }

    public function destroy(Media $media): JsonResponse
    {
        Gate::authorize('delete', $media);
        Storage::disk($media->disk)->delete(array_filter([$media->path, $media->thumbnail_path]));
        $media->delete();

        return response()->json(['message' => 'Media deleted.']);
    }
}
