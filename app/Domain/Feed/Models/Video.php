<?php

namespace App\Domain\Feed\Models;

use App\Domain\Moderation\Models\ContentModerationAppeal;
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
        return ['hashtags' => 'array', 'skill_tags' => 'array', 'content_labels' => 'array', 'content_embedding' => 'array', 'analyzed_at' => 'datetime', 'moderation_analyzed_at' => 'datetime', 'published_at' => 'datetime', 'expires_at' => 'datetime', 'sports_relevance_score' => 'float', 'comments_enabled' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
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
        return $this->belongsTo(config('modules.media_model'));
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(config('modules.media_model'), 'video_images')->withPivot(['position', 'is_cover'])->orderByPivot('position');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function moderationAppeals(): HasMany
    {
        return $this->hasMany(ContentModerationAppeal::class);
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
