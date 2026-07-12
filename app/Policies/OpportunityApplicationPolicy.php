<?php

namespace App\Policies;

use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Models\User;

class OpportunityApplicationPolicy
{
    public function view(User $user, OpportunityApplication $application): bool
    {
        return $user->id === $application->user_id || $user->id === $application->opportunity->posted_by_id || $user->hasRole('admin');
    }

    public function review(User $user, OpportunityApplication $application): bool
    {
        return $user->id === $application->opportunity->posted_by_id || $user->hasRole('admin');
    }
}
