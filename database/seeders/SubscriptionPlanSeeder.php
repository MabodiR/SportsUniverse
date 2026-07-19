<?php

namespace Database\Seeders;

use App\Domain\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name'=>'Free','slug'=>'free','tagline'=>'Start your sporting journey','description'=>'Everything SportsUniverse offers today, with generous limits for athletes, supporters and grassroots teams.','monthly_price_cents'=>0,'annual_price_cents'=>0,'accent'=>'cyan','sort_order'=>1,'features'=>['Create a complete sporting profile','Upload videos, photos and career highlights','Discover talent and opportunities','Follow, message, save and apply','Go live to up to 1,000 viewers','Standard analytics and media optimisation','Community support'],'limits'=>['live_viewers'=>1000,'storage_gb'=>5,'workspace_seats'=>1,'analytics_days'=>30,'live_hours_monthly'=>5,'hd_quality'=>'1080p','discovery_boosts_monthly'=>0,'bulk_invites_monthly'=>0,'data_exports'=>false,'branded_live'=>false]],
            ['name'=>'Pro','slug'=>'pro','tagline'=>'Turn potential into momentum','description'=>'A professional growth engine for athletes, coaches, scouts and creators who want to be seen, understood and contacted.','monthly_price_cents'=>14900,'annual_price_cents'=>149000,'accent'=>'primary','sort_order'=>2,'is_featured'=>true,'features'=>['Everything in Free','Live events for up to 10,000 viewers','Advanced performance and audience analytics','Pro discovery badge and verification workflow','Monthly organic discovery spotlight','Downloadable athlete CV and media portfolio','Full-HD priority media processing','Private scout links and profile visitor insights','Scheduling for posts and live events','Priority support'],'limits'=>['live_viewers'=>10000,'storage_gb'=>50,'workspace_seats'=>3,'analytics_days'=>365,'live_hours_monthly'=>40,'hd_quality'=>'1080p priority','discovery_boosts_monthly'=>2,'bulk_invites_monthly'=>50,'data_exports'=>true,'branded_live'=>false]],
            ['name'=>'Elite','slug'=>'elite','tagline'=>'Operate at professional scale','description'=>'The command centre for clubs, academies, agencies, sponsors and event operators building the future of sport.','monthly_price_cents'=>49900,'annual_price_cents'=>499000,'accent'=>'navy','sort_order'=>3,'features'=>['Everything in Pro','Live events for up to 100,000 viewers','Branded live rooms, sponsor overlays and replays','15-seat club or agency workspace','Recruiting pipelines, shortlists and comparison boards','Bulk trial invitations and applicant scoring','Cross-team analytics and scheduled reports','Talent database and CSV/PDF exports','Dedicated onboarding and priority moderation','Early access to new professional tools'],'limits'=>['live_viewers'=>100000,'storage_gb'=>500,'workspace_seats'=>15,'analytics_days'=>1095,'live_hours_monthly'=>200,'hd_quality'=>'4K source / 1080p delivery','discovery_boosts_monthly'=>10,'bulk_invites_monthly'=>1000,'data_exports'=>true,'branded_live'=>true]],
        ];
        foreach ($plans as $plan) SubscriptionPlan::query()->updateOrCreate(['slug'=>$plan['slug']], $plan);
    }
}
