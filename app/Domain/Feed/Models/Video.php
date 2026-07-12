<?php

namespace App\Domain\Feed\Models;

use App\Domain\Media\Models\Media;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): Factory
    {
        return VideoFactory::new();
    }

    protected function casts(): array
    {
        return ['hashtags' => 'array', 'published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'video_images')->withPivot(['position', 'is_cover'])->orderByPivot('position');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_likes')->withPivot('created_at');
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_videos')->withPivot('created_at');
    }
}
