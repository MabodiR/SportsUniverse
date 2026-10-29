<?php

namespace App\Domain\Promotions\Models;

use App\Domain\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = ['code','campaign_name','description','discount_type','discount_value','max_redemptions','max_redemptions_per_user','redemptions_count','starts_at','expires_at','is_active','new_users_only','auto_apply','is_archived'];
    protected function casts(): array { return ['starts_at'=>'datetime','expires_at'=>'datetime','is_active'=>'boolean','new_users_only'=>'boolean','auto_apply'=>'boolean','is_archived'=>'boolean']; }
    public function plans(): BelongsToMany { return $this->belongsToMany(SubscriptionPlan::class); }
    public function redemptions(): HasMany { return $this->hasMany(VoucherRedemption::class); }
    public function announcements(): HasMany { return $this->hasMany(PromotionAnnouncement::class); }
    public function audits(): HasMany { return $this->hasMany(VoucherAudit::class); }
    public function getRemainingAttribute(): ?int { return $this->max_redemptions === null ? null : max(0, $this->max_redemptions - $this->redemptions_count); }
    public function available(): bool { return $this->is_active && ! $this->is_archived && (! $this->starts_at || $this->starts_at->isPast()) && (! $this->expires_at || $this->expires_at->isFuture()) && ($this->max_redemptions === null || $this->redemptions_count < $this->max_redemptions); }
    protected static function booted(): void
    {
        static::created(fn (self $voucher) => $voucher->audits()->create(['admin_user_id'=>auth()->id(),'action'=>'created','changes'=>$voucher->getAttributes()]));
        static::updated(function (self $voucher) { if ($voucher->getChanges()) $voucher->audits()->create(['admin_user_id'=>auth()->id(),'action'=>'updated','changes'=>$voucher->getChanges()]); });
    }
}
