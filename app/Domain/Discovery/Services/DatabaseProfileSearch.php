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
            $like = '%'.mb_strtolower($q).'%';
            $query->where(fn ($builder) => $builder->whereRaw('LOWER(users.name) LIKE ?', [$like])->orWhereHas('profile', fn ($p) => $p->whereRaw('LOWER(bio) LIKE ?', [$like])));
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
