<?php
namespace App\Domain\Promotions\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PromotionAnnouncement extends Model
{
    protected $fillable=['voucher_id','title','message','cta_label','cta_url','show_banner','show_popup','is_active','starts_at','ends_at'];
    protected function casts(): array { return ['show_banner'=>'boolean','show_popup'=>'boolean','is_active'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime']; }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
}
