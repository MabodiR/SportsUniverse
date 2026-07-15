<?php

namespace App\Domain\Profiles\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteCareerEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date', 'is_current' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
