<?php

namespace App\Policies;

use App\Domain\Opportunities\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function view(?User $user, Opportunity $opportunity): bool
    {
        return $opportunity->status === 'published' || $user?->id === $opportunity->posted_by_id || $user?->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['club', 'academy', 'business', 'sponsor', 'admin']);
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->posted_by_id || $user->hasRole('admin');
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $this->update($user, $opportunity);
    }

    public function apply(User $user, Opportunity $opportunity): bool
    {
        return $user->id !== $opportunity->posted_by_id && $opportunity->status === 'published' && (! $opportunity->deadline || $opportunity->deadline->isFuture());
    }
}
