<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        foreach (['athlete', 'fan', 'coach', 'referee', 'linesman', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor', 'admin', 'system_admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->call(SportSeeder::class);
        $this->call(FeedDemoSeeder::class);
        $this->call(OpportunityDemoSeeder::class);
        $user = User::factory()->create(['name' => 'SportUniverse Admin', 'email' => 'admin@sportuniverse.test']);
        $user->assignRole('admin');
    }
}
