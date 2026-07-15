<?php

namespace App\Http\Controllers\Api\V1\Opportunities;

use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Opportunities\Actions\SubmitOpportunityApplication;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Opportunities\ApplyOpportunityRequest;
use App\Http\Requests\Api\V1\Opportunities\ReviewApplicationRequest;
use App\Http\Resources\Api\V1\Opportunities\OpportunityApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OpportunityApplicationController extends Controller
{
    public function store(ApplyOpportunityRequest $request, Opportunity $opportunity, SubmitOpportunityApplication $submit): JsonResponse
    {
        Gate::authorize('apply', $opportunity);
        $application = $submit->execute($request->user()->load('profile'), $opportunity, $request->validated());

        return response()->json(['message' => 'Application submitted.', 'data' => new OpportunityApplicationResource($this->load($application))], 201);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return OpportunityApplicationResource::collection($request->user()->hasMany(OpportunityApplication::class)->with('opportunity.poster.profile', 'opportunity.poster.organisationProfile', 'opportunity.sport', 'opportunity.position', 'user.profile', 'resume', 'statusHistory')->latest()->paginate(20));
    }

    public function applicants(Opportunity $opportunity): AnonymousResourceCollection
    {
        Gate::authorize('update', $opportunity);

        return OpportunityApplicationResource::collection($opportunity->applications()->with('opportunity.poster.profile', 'opportunity.poster.organisationProfile', 'opportunity.sport', 'opportunity.position', 'user.profile', 'resume', 'statusHistory')->latest()->paginate(30));
    }

    public function review(ReviewApplicationRequest $request, OpportunityApplication $application, NotificationDispatcher $notifications): JsonResponse
    {
        Gate::authorize('review', $application);
        $application->update(['status' => $request->validated('status'), 'reviewer_notes' => $request->validated('reviewer_notes'), 'reviewed_at' => now()]);
        $application->statusHistory()->create(['changed_by_id' => $request->user()->id, 'status' => $application->status, 'notes' => $application->reviewer_notes]);
        $application->load('opportunity');
        $notifications->send($application->user, 'opportunities', ['event' => 'opportunity_application_status', 'application_id' => $application->public_id, 'opportunity_id' => $application->opportunity->public_id, 'opportunity_title' => $application->opportunity->title, 'status' => $application->status]);

        return response()->json(['message' => 'Application status updated.', 'data' => new OpportunityApplicationResource($this->load($application))]);
    }

    public function withdraw(Request $request, OpportunityApplication $application): JsonResponse
    {
        abort_unless($application->user_id === $request->user()->id, 403);
        abort_if(in_array($application->status, ['accepted', 'rejected', 'withdrawn'], true), 422, 'This application can no longer be withdrawn.');
        $application->update(['status' => 'withdrawn']);
        $application->statusHistory()->create(['changed_by_id' => $request->user()->id, 'status' => 'withdrawn']);

        return response()->json(['message' => 'Application withdrawn.', 'data' => new OpportunityApplicationResource($this->load($application))]);
    }

    private function load(OpportunityApplication $application): OpportunityApplication
    {
        return $application->load('opportunity.poster.profile', 'opportunity.poster.organisationProfile', 'opportunity.sport', 'opportunity.position', 'user.profile', 'resume', 'statusHistory');
    }
}
