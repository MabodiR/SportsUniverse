<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ProductionAccessUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = (string) env('PRODUCTION_ADMIN_PASSWORD', '');
        $accounts = [
            ['name' => 'SportsUniverse Super Admin', 'email' => 'superadmin@sportsuniverse.co.za', 'slug' => 'sportsuniverse-super-admin', 'role' => 'super_admin'],
            ['name' => 'SportsUniverse Athlete Demo', 'email' => 'athlete.demo@sportsuniverse.co.za', 'slug' => 'sportsuniverse-athlete-demo', 'role' => 'athlete'],
            ['name' => 'SportsUniverse Sponsor Demo', 'email' => 'sponsor.demo@sportsuniverse.co.za', 'slug' => 'sportsuniverse-sponsor-demo', 'role' => 'sponsor'],
        ];

        $created = [];
        foreach ($accounts as $account) {
            Role::findOrCreate($account['role'], 'web');
            $user = User::where('email', $account['email'])->first();
            if (! $user) {
                $password = $account['role'] === 'super_admin' && $adminPassword !== ''
                    ? $adminPassword
                    : Str::password(24, symbols: true);
                $user = User::create([
                    'name' => $account['name'], 'email' => $account['email'],
                    'password' => Hash::make($password), 'status' => 'active',
                    'email_verified_at' => now(), 'onboarding_completed_at' => now(),
                ]);
                $created[] = [$account['email'], $account['role'], $password];
            } else {
                $user->update(['name' => $account['name'], 'status' => 'active', 'email_verified_at' => $user->email_verified_at ?? now(), 'onboarding_completed_at' => $user->onboarding_completed_at ?? now()]);
                if ($account['role'] === 'super_admin' && $adminPassword !== '') {
                    $user->update(['password' => Hash::make($adminPassword)]);
                }
            }
            $user->syncRoles([$account['role']]);
            $user->profile()->updateOrCreate([], ['slug' => $account['slug'], 'is_public' => true]);
        }

        if ($created === []) {
            $this->command?->warn('All three production access accounts already exist. Their passwords were not changed.');

            return;
        }

        $this->command?->newLine();
        $this->command?->warn('Save these one-time credentials now. Passwords will not be printed again.');
        $this->command?->table(['Email', 'Role', 'One-time password'], $created);
    }
}
