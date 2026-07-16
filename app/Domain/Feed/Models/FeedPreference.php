<?php

namespace App\Domain\Feed\Models;

use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedPreference extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['metadata' => 'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function video(): BelongsTo { return $this->belongsTo(Video::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function sport(): BelongsTo { return $this->belongsTo(Sport::class); }
}
