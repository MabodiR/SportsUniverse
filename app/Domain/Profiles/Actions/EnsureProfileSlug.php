<?php

namespace App\Domain\Profiles\Actions;

use App\Domain\Profiles\Models\UserProfile;
use App\Models\User;
use Illuminate\Support\Str;

class EnsureProfileSlug
{
    public function execute(User $user): string
    {
        if ($user->profile?->slug) {
            return $user->profile->slug;
        }
        $base = Str::slug($user->name) ?: 'member';
        $slug = $base;
        $suffix = 1;
        while (UserProfile::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }
        $user->profile()->updateOrCreate([], ['slug' => $slug]);

        return $slug;
    }
}
