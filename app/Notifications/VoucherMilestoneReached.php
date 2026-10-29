<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class VoucherMilestoneReached extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public string $campaign,public int $count,public int $limit){}
    public function via(object $notifiable):array{return['mail','database'];}
    public function toMail(object $notifiable):MailMessage{return(new MailMessage)->subject("SportsUniverse promotion milestone: {$this->count}")->line("{$this->campaign} has reached {$this->count} of {$this->limit} redemptions.")->action('Open voucher management',url('/admin/vouchers'));}
    public function toArray(object $notifiable):array{return['title'=>'Promotion milestone reached','body'=>"{$this->campaign}: {$this->count} of {$this->limit} claimed."];}
}
