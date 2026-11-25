<?php

namespace App\Filament\Pages;

use App\Domain\Auth\Services\RegistrationFlow;
use App\Models\PendingRegistration;
use App\Models\RegistrationSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Registrations extends Page
{
    use WithPagination;

    protected static ?string $title = 'Registrations';

    protected string $view = 'filament.pages.registrations';

    public bool $verificationRequired = false;

    public bool $whatsappEnabled = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'super_admin', 'system_admin']);
    }

    public function mount(): void
    {
        $settings = RegistrationSetting::current();
        $this->verificationRequired = $settings->verification_required;
        $this->whatsappEnabled = $settings->whatsapp_enabled;
    }

    public function saveSettings(): void
    {
        RegistrationSetting::applyPolicy(auth()->user(), ['verification_required' => $this->verificationRequired, 'whatsapp_enabled' => $this->whatsappEnabled]);
        Notification::make()->title('Registration settings saved')->success()->send();
    }

    public function approve(string $id): void
    {
        abort_unless(static::canAccess(), 403);
        app(RegistrationFlow::class)->complete(PendingRegistration::findOrFail($id), false, auth()->id());
    }

    public function getPendingProperty()
    {
        return PendingRegistration::where('status', 'pending')->latest()->orderBy('id')->paginate(25);
    }
}
