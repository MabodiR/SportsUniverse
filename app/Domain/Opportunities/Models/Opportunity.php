<?php

namespace App\Domain\Opportunities\Models;

use App\Domain\Sports\Models\Position;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): Factory
    {
        return OpportunityFactory::new();
    }

    protected function casts(): array
    {
        return ['requirements' => 'array', 'required_documents' => 'array', 'is_remote' => 'boolean', 'published_at' => 'datetime', 'deadline' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(OpportunityApplication::class);
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_opportunities')->withPivot('created_at');
    }
}
