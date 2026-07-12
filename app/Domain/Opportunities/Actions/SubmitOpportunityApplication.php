<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Media\Models\Media;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitOpportunityApplication
{
    public function execute(User $user, Opportunity $opportunity, array $data): OpportunityApplication
    {
        if (OpportunityApplication::where(['opportunity_id' => $opportunity->id, 'user_id' => $user->id])->exists()) {
            throw ValidationException::withMessages(['opportunity' => ['You have already applied for this opportunity.']]);
        }$age = $user->profile?->date_of_birth?->age;
        if ($opportunity->minimum_age && ($age === null || $age < $opportunity->minimum_age)) {
            throw ValidationException::withMessages(['profile' => ['Your profile does not meet the minimum age requirement.']]);
        }if ($opportunity->maximum_age && ($age === null || $age > $opportunity->maximum_age)) {
            throw ValidationException::withMessages(['profile' => ['Your profile does not meet the maximum age requirement.']]);
        }$media = null;
        if (isset($data['resume_media_id'])) {
            $media = Media::where('public_id', $data['resume_media_id'])->where('user_id', $user->id)->where('processing_status', 'ready')->where('moderation_status', 'approved')->first();
            if (! $media) {
                throw ValidationException::withMessages(['resume_media_id' => ['Use an owned, approved document that has finished processing.']]);
            }
        }

return DB::transaction(function () use ($user, $opportunity, $data, $media) {
            $locked = Opportunity::lockForUpdate()->findOrFail($opportunity->id);
            if ($locked->status !== 'published' || ($locked->deadline && $locked->deadline->isPast())) {
                throw ValidationException::withMessages(['opportunity' => ['This opportunity is no longer accepting applications.']]);
            }$application = $locked->applications()->create(['public_id' => (string) Str::ulid(), 'user_id' => $user->id, 'resume_media_id' => $media?->id, 'cover_letter' => $data['cover_letter'] ?? null, 'status' => 'submitted']);
            $locked->increment('applications_count');

            return $application;
        });
    }
}
