<?php

namespace Database\Seeders;

use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;

class OpportunityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $club = User::factory()->create(['name' => 'SportsUniverse Academy', 'email' => 'academy@sportuniverse.test']);
        $club->assignRole('academy');
        $club->profile()->create(['slug' => 'sportuniverse-academy', 'country' => 'ZA', 'city' => 'Johannesburg', 'completeness' => 100]);
        $club->organisationProfile()->create(['organisation_name' => 'SportsUniverse Academy', 'organisation_type' => 'academy', 'contact_email' => $club->email]);
        $sports = Sport::with('positions')->get();
        foreach (range(1, 5) as $index) {
            $sport = $sports->get(($index - 1) % $sports->count());
            Opportunity::factory()->for($club, 'poster')->create(['sport_id' => $sport?->id, 'position_id' => $sport?->positions->first()?->id, 'title' => ($sport?->name ?? 'Open').' Talent Opportunity '.$index]);
        }
    }
}
