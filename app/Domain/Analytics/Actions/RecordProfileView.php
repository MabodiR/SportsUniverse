<?php

namespace App\Domain\Analytics\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordProfileView
{
    public function execute(User $viewer, User $profile, string $source = 'profile'): bool
    {
        if ($viewer->is($profile)) {
            return false;
        }

return DB::transaction(function () use ($viewer, $profile, $source) {
            $created = DB::table('profile_views')->insertOrIgnore(['profile_user_id' => $profile->id, 'viewer_id' => $viewer->id, 'source' => $source, 'viewed_on' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]) === 1;
            if ($created) {
                $profile->profile()->increment('views_count');
            }

return $created;
        });
    }
}
