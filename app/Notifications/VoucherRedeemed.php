<?php
namespace App\Notifications;
use App\Domain\Promotions\Models\VoucherRedemption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class VoucherRedeemed extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public int $redemptionId) {}
    public function via(object $notifiable):array{return['mail','database'];}
    public function toMail(object $notifiable):MailMessage{$r=VoucherRedemption::with('plan','voucher')->findOrFail($this->redemptionId);return(new MailMessage)->subject('Your free SportsUniverse membership is active')->greeting('Welcome, '.$notifiable->name.'!')->line("Your {$r->plan->name} membership is now active through the {$r->voucher->campaign_name} promotion.")->line('Amount due: R0. No payment details are required.')->action('Explore SportsUniverse',url('/feed'));}
    public function toArray(object $notifiable):array{$r=VoucherRedemption::with('plan')->findOrFail($this->redemptionId);return['title'=>'Free membership activated','body'=>"Your {$r->plan->name} membership is active.",'voucher_redemption_id'=>$r->id];}
}
