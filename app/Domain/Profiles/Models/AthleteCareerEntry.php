<?php

namespace App\Domain\Profiles\Models;

use App\Domain\Sports\Models\Position;
use App\Domain\Sports\Models\Sport;
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

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
