<?php

namespace App\Domain\Profiles\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['services' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
