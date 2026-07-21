<?php

namespace App\Domain\Advertising\Services;

use App\Domain\Advertising\Models\AdCampaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayFastGateway
{
    public function checkout(AdCampaign $campaign, User $user): array
    {
        abort_unless(config('payfast.enabled') && config('payfast.merchant_id') && config('payfast.merchant_key'), 503, 'PayFast payments are not configured.');
        $payment = $campaign->payments()->where('status', 'pending')->latest()->first();
        if (! $payment) {
            $id = (string) Str::ulid();
            $payment = $campaign->payments()->create([
                'public_id' => $id, 'user_id' => $user->id, 'merchant_reference' => 'SU-'.$id,
                'amount_cents' => $campaign->total_budget_cents, 'status' => 'pending',
            ]);
        }
        [$first, $last] = $this->names($user->name);
        $data = [
            'merchant_id' => (string) config('payfast.merchant_id'),
            'merchant_key' => (string) config('payfast.merchant_key'),
            'return_url' => route('payfast.return', ['payment' => $payment->public_id]),
            'cancel_url' => route('payfast.cancel', ['payment' => $payment->public_id]),
            'notify_url' => route('payfast.notify'),
            'name_first' => $first,
            'name_last' => $last,
            'email_address' => (string) $user->email,
            'm_payment_id' => $payment->merchant_reference,
            'amount' => number_format($payment->amount_cents / 100, 2, '.', ''),
            'item_name' => Str::limit('SportsUniverse campaign: '.$campaign->title, 100, ''),
            'item_description' => Str::limit($campaign->description ?: $campaign->campaign_type.' campaign', 255, ''),
            'custom_str1' => $campaign->public_id,
            'custom_str2' => $payment->public_id,
        ];
        $data = $this->normalized($data);
        $data['signature'] = $this->signature($data);

        return ['provider' => 'payfast', 'sandbox' => config('payfast.sandbox'), 'action' => config('payfast.process_url'), 'method' => 'POST', 'fields' => $data];
    }

    public function signature(array $data): string
    {
        unset($data['signature']);
        $string = collect($this->normalized($data))->filter(fn ($value) => $value !== '' && $value !== null)
            ->map(fn ($value, $key) => $key.'='.urlencode((string) $value))->implode('&');
        if ($this->passphrase() !== null) {
            $string .= '&passphrase='.urlencode($this->passphrase());
        }

        return md5($string);
    }

    public function validSignature(array $payload): bool
    {
        return isset($payload['signature']) && hash_equals(strtolower((string) $payload['signature']), $this->signature($payload));
    }

    public function validServerConfirmation(array $payload): bool
    {
        if (! config('payfast.validate_server')) {
            return true;
        }
        $body = collect($payload)->except('signature')->filter(fn ($value) => $value !== '' && $value !== null)
            ->map(fn ($value, $key) => $key.'='.urlencode((string) $value))->implode('&');

        return trim(Http::timeout(15)->withBody($body, 'application/x-www-form-urlencoded')->post(config('payfast.validate_url'))->body()) === 'VALID';
    }

    public function validSource(Request $request): bool
    {
        if (! config('payfast.validate_ip')) {
            return true;
        }
        $valid = collect(config('payfast.valid_hosts'))->flatMap(fn ($host) => gethostbynamel($host) ?: [])->unique();

        return $valid->contains($request->ip());
    }

    private function names(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?: 'SportsUniverse', $parts[1] ?? 'Member'];
    }

    private function normalized(array $data): array
    {
        return collect($data)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
    }

    private function passphrase(): ?string
    {
        $passphrase = trim((string) config('payfast.passphrase'));

        return $passphrase === '' ? null : $passphrase;
    }
}
