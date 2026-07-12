<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Contracts\ProfileIndexer;
use App\Domain\Discovery\Contracts\ProfileSearchEngine;
use App\Models\User;

class DatabaseProfileSearch implements ProfileIndexer, ProfileSearchEngine
{
    public function search(array $criteria): array
    {
        $query = User::query()->where('status', 'active')->whereHas('profile', fn ($q) => $q->where('is_public', true))->with('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'professionalProfile', 'organisationProfile');
        if ($q = $criteria['q'] ?? null) {
            $term = mb_strtolower(trim($q));
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like, $term) {
                $builder->whereRaw('LOWER(users.name) LIKE ?', [$like])
                    ->orWhereHas('profile', fn ($profile) => $profile->where(function ($fields) use ($like) {
                        foreach (['bio', 'gender', 'country', 'province', 'city', 'locality', 'township'] as $field) {
                            $fields->orWhereRaw("LOWER({$field}) LIKE ?", [$like]);
                        }
                    }))
                    ->orWhereHas('roles', fn ($roles) => $roles->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('athleteProfile', fn ($athlete) => $athlete
                        ->where(fn ($fields) => $fields->whereRaw('LOWER(primary_sport) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(position) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(club_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(playing_level) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(dominant_side) LIKE ?', [$like]))
                        ->orWhereHas('sport', fn ($sport) => $sport->whereRaw('LOWER(name) LIKE ?', [$like]))
                        ->orWhereHas('taxonomyPosition', fn ($position) => $position->whereRaw('LOWER(name) LIKE ?', [$like])))
                    ->orWhereHas('professionalProfile', fn ($professional) => $professional->whereRaw('LOWER(professional_type) LIKE ?', [$like])->orWhereRaw('LOWER(specialisation) LIKE ?', [$like]))
                    ->orWhereHas('organisationProfile', fn ($organisation) => $organisation->whereRaw('LOWER(organisation_name) LIKE ?', [$like])->orWhereRaw('LOWER(organisation_type) LIKE ?', [$like])->orWhereRaw('LOWER(services) LIKE ?', [$like]))
                    ->orWhereHas('videos', fn ($videos) => $videos->where('status', 'published')->where('visibility', 'public')->where(fn ($content) => $content->whereRaw('LOWER(caption) LIKE ?', [$like])->orWhereRaw('LOWER(hashtags) LIKE ?', [$like])))
                    ->orWhereHas('postedOpportunities', fn ($opportunities) => $opportunities->where('status', 'published')->where(fn ($content) => $content->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(description) LIKE ?', [$like])->orWhereRaw('LOWER(city) LIKE ?', [$like])->orWhereRaw('LOWER(province) LIKE ?', [$like])->orWhereRaw('LOWER(requirements) LIKE ?', [$like])));

                if (ctype_digit($term) && (int) $term >= 5 && (int) $term <= 100) {
                    $age = (int) $term;
                    $builder->orWhereHas('profile', fn ($profile) => $profile
                        ->whereDate('date_of_birth', '<=', today()->subYears($age))
                        ->whereDate('date_of_birth', '>=', today()->subYears($age + 1)->addDay()));
                }
            });
            $query->orderByRaw('CASE WHEN LOWER(users.name) = ? THEN 0 WHEN LOWER(users.name) LIKE ? THEN 1 ELSE 2 END', [mb_strtolower($q), mb_strtolower($q).'%']);
        }if ($role = $criteria['role'] ?? null) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $role));
        }$query->when($criteria['sport_id'] ?? null, fn ($q, $v) => $q->whereHas('athleteProfile', fn ($a) => $a->where('sport_id', $v)));
        $query->when($criteria['position_id'] ?? null, fn ($q, $v) => $q->whereHas('athleteProfile', fn ($a) => $a->where('position_id', $v)));
        $query->when($criteria['gender'] ?? null, fn ($q, $v) => $q->whereHas('profile', fn ($p) => $p->where('gender', $v)));
        foreach (['country', 'province', 'city', 'locality', 'township'] as $field) {
            $query->when($criteria[$field] ?? null, fn ($q, $v) => $q->whereHas('profile', fn ($p) => $p->where($field, $v)));
        }$query->when(array_key_exists('available', $criteria), fn ($q) => $q->whereHas('profile', fn ($p) => $p->where('is_available', $criteria['available'])));
        $query->when($criteria['min_completeness'] ?? null, fn ($q, $v) => $q->whereHas('profile', fn ($p) => $p->where('completeness', '>=', $v)));
        $query->when($criteria['club'] ?? null, fn ($q, $v) => $q->whereHas('athleteProfile', fn ($a) => $a->where('club_name', 'like', '%'.$v.'%')));
        if (isset($criteria['min_age'])) {
            $query->whereHas('profile', fn ($p) => $p->whereDate('date_of_birth', '<=', today()->subYears($criteria['min_age'])));
        }if (isset($criteria['max_age'])) {
            $query->whereHas('profile', fn ($p) => $p->whereDate('date_of_birth', '>=', today()->subYears($criteria['max_age'] + 1)->addDay()));
        }$query->orderByDesc('id');
        $page = $query->paginate($criteria['per_page'], ['*'], 'page', $criteria['page']);

        return ['items' => $page->getCollection(), 'total' => $page->total()];
    }

    public function index(User $user): void {}

    public function delete(User $user): void {}
}
