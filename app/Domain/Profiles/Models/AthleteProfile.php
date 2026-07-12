<?php

namespace App\Domain\Profiles\Models;

use App\Domain\Sports\Models\Position;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteProfile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function taxonomyPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}
