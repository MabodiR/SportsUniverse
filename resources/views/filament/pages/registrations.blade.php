<x-filament-panels::page>
    @if(auth()->user()->hasAnyRole(['super_admin','system_admin']))
    <form wire:submit="saveSettings" class="space-y-4">
        <label><input type="checkbox" wire:model="verificationRequired"> Require email or WhatsApp verification</label><br>
        <label><input type="checkbox" wire:model="whatsappEnabled"> Offer WhatsApp OTP</label>
        @error('whatsapp_enabled') <p>{{ $message }}</p> @enderror
        <p>When verification is off, new members can register immediately.</p>
        <x-filament::button type="submit">Save registration settings</x-filament::button>
    </form>
    @endif
    <h2>Pending registrations</h2>
    @forelse($this->pending as $pending)
    <x-filament::section wire:key="{{ $pending->id }}">
        <strong>{{ $pending->name }}</strong><p>{{ $pending->email }} · {{ $pending->phone }}</p><p>{{ $pending->channel }} · {{ $pending->delivery_status }} · {{ $pending->created_at }}</p>
        <x-filament::button wire:click="approve('{{ $pending->id }}')" wire:confirm="Approve this registration and allow this member to sign in?">Approve registration</x-filament::button>
    </x-filament::section>
    @empty <p>No pending registrations.</p> @endforelse
{{ $this->pending->links() }}
</x-filament-panels::page>
