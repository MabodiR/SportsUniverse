<?php

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Feed\Models\Comment;
use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Moderation\Models\Report;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Moderation\StoreReportRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        $target = match ($request->validated('type')) {
            'video' => Video::where('public_id', $request->validated('id'))->firstOrFail(),'comment' => Comment::where('public_id', $request->validated('id'))->firstOrFail(),'media' => Media::where('public_id', $request->validated('id'))->firstOrFail(),'user' => User::findOrFail((int) $request->validated('id'))
        };
        abort_if($target instanceof User && $target->is($request->user()), 422, 'You cannot report yourself.');
        $report = Report::create(['public_id' => (string) Str::ulid(), 'reporter_id' => $request->user()->id, 'reportable_type' => $target->getMorphClass(), 'reportable_id' => $target->getKey(), 'reason' => $request->validated('reason'), 'details' => $request->validated('details'), 'status' => 'open']);

        return response()->json(['message' => 'Report submitted.', 'data' => ['id' => $report->public_id, 'status' => $report->status]], 201);
    }
}
