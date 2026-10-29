<?php
namespace App\Domain\Promotions\Models;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class VoucherRedemption extends Model
{
    protected $fillable=['voucher_id','user_id','subscription_plan_id','subscription_id','original_amount_cents','discount_amount_cents','final_amount_cents','redeemed_at'];
    protected function casts(): array { return ['redeemed_at'=>'datetime']; }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class,'subscription_plan_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
}
