<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RegistrationSetting extends Model
{
    protected $guarded = [];

    protected $attributes = ['verification_required' => false, 'whatsapp_enabled' => false];

    protected function casts(): array
    {
        return ['verification_required' => 'boolean', 'whatsapp_enabled' => 'boolean'];
    }

    public static function applyPolicy(User $actor, array $data): void
    {
        abort_unless($actor->hasAnyRole(['super_admin', 'system_admin']), 403);
        if ($data['whatsapp_enabled'] && (! config('services.registration_whatsapp.token') || ! config('services.registration_whatsapp.phone_id') || ! config('services.registration_whatsapp.template'))) {
            throw ValidationException::withMessages(['whatsapp_enabled' => 'Configure WhatsApp Business credentials and an approved authentication template first.']);
        }
        static::current()->update(['verification_required' => (bool) $data['verification_required'], 'whatsapp_enabled' => (bool) $data['whatsapp_enabled'], 'updated_by' => $actor->id]);
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
