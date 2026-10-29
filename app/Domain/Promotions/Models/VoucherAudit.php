<?php
namespace App\Domain\Promotions\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class VoucherAudit extends Model
{
    protected $fillable=['voucher_id','admin_user_id','action','changes'];
    protected function casts(): array { return ['changes'=>'array']; }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function admin(): BelongsTo { return $this->belongsTo(User::class,'admin_user_id'); }
}
