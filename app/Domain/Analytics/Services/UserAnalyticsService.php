<?php

namespace App\Domain\Analytics\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserAnalyticsService
{
    public function dashboard(array $filters): array
    {
        $base = $this->base($filters);
        $total = (clone $base)->distinct('users.id')->count('users.id');
        $sources = collect(['mobile', 'web', 'unknown'])->mapWithKeys(fn (string $source) => [
            $source => $this->sourceCount(clone $base, $source),
        ]);

        return [
            'summary' => [
                'total' => $total,
                'active' => (clone $base)->where('users.status', 'active')->distinct('users.id')->count('users.id'),
                'verified' => (clone $base)->whereNotNull('users.email_verified_at')->distinct('users.id')->count('users.id'),
                'complete_profiles' => (clone $base)->where('user_profiles.completeness', '>=', 80)->distinct('users.id')->count('users.id'),
                'mobile' => $sources['mobile'],
                'web' => $sources['web'],
                'unknown_source' => $sources['unknown'],
                'mobile_share' => ($sources['mobile'] + $sources['web']) > 0 ? round($sources['mobile'] / ($sources['mobile'] + $sources['web']) * 100, 1) : 0,
            ],
            'trend' => $this->trend(clone $base),
            'sources' => $sources->map(fn (int $count, string $label) => ['label' => ucfirst($label), 'count' => $count])->values()->all(),
            'devices' => $this->historyGroup(clone $base, 'device_type'),
            'platforms' => $this->historyGroup(clone $base, 'platform'),
            'browsers' => $this->historyGroup(clone $base, 'browser'),
            'roles' => $this->roles(clone $base),
            'locations' => $this->group(clone $base, 'user_profiles.province', 10),
            'cities' => $this->group(clone $base, 'user_profiles.city', 10),
            'sports' => $this->group(clone $base, 'sports.name', 10),
            'ages' => $this->ages(clone $base),
            'genders' => $this->group(clone $base, 'user_profiles.gender', 8),
            'statuses' => $this->group(clone $base, 'users.status', 8),
            'profile_coverage' => [
                'location' => (clone $base)->whereNotNull('user_profiles.province')->distinct('users.id')->count('users.id'),
                'age' => (clone $base)->whereNotNull('user_profiles.date_of_birth')->distinct('users.id')->count('users.id'),
                'sport' => (clone $base)->whereNotNull('athlete_profiles.sport_id')->distinct('users.id')->count('users.id'),
            ],
        ];
    }

    public function filterOptions(): array
    {
        return [
            'roles' => DB::table('roles')->orderBy('name')->pluck('name')->all(),
            'provinces' => DB::table('user_profiles')->whereNotNull('province')->where('province', '!=', '')->distinct()->orderBy('province')->pluck('province')->all(),
            'cities' => DB::table('user_profiles')->whereNotNull('city')->where('city', '!=', '')->distinct()->orderBy('city')->limit(250)->pluck('city')->all(),
            'sports' => DB::table('sports')->orderBy('name')->get(['id', 'name'])->map(fn ($sport) => ['id' => $sport->id, 'name' => $sport->name])->all(),
        ];
    }

    private function base(array $filters): Builder
    {
        $query = User::query()
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->leftJoin('athlete_profiles', 'athlete_profiles.user_id', '=', 'users.id')
            ->leftJoin('sports', 'sports.id', '=', 'athlete_profiles.sport_id')
            ->select('users.*');

        if ($days = (int) ($filters['period'] ?? 0)) {
            $query->where('users.created_at', '>=', now()->subDays($days));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('users.status', $status);
        }
        if ($province = $filters['province'] ?? null) {
            $query->where('user_profiles.province', $province);
        }
        if ($city = $filters['city'] ?? null) {
            $query->where('user_profiles.city', $city);
        }
        if ($gender = $filters['gender'] ?? null) {
            $query->where('user_profiles.gender', $gender);
        }
        if ($sport = $filters['sport'] ?? null) {
            $query->where('athlete_profiles.sport_id', $sport);
        }
        if ($role = $filters['role'] ?? null) {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role));
        }
        if ($source = $filters['source'] ?? null) {
            $this->applySource($query, $source);
        }

        if (filled($filters['age_min'] ?? null)) {
            $query->whereDate('user_profiles.date_of_birth', '<=', now()->subYears((int) $filters['age_min'])->toDateString());
        }
        if (filled($filters['age_max'] ?? null)) {
            $query->whereDate('user_profiles.date_of_birth', '>=', now()->subYears((int) $filters['age_max'] + 1)->addDay()->toDateString());
        }

        return $query;
    }

    private function applySource(Builder $query, string $source): void
    {
        if ($source === 'unknown') {
            $query->whereNotExists(fn ($history) => $history->selectRaw('1')->from('login_histories')->whereColumn('login_histories.user_id', 'users.id')->where('method', 'registration'));

            return;
        }

        $types = $source === 'mobile' ? ['mobile', 'tablet'] : ['desktop'];
        $query->whereExists(fn ($history) => $history->selectRaw('1')->from('login_histories')->whereColumn('login_histories.user_id', 'users.id')->where('method', 'registration')->whereIn('device_type', $types));
    }

    private function sourceCount(Builder $query, string $source): int
    {
        $this->applySource($query, $source);

        return $query->distinct('users.id')->count('users.id');
    }

    private function historyGroup(Builder $query, string $column): array
    {
        $users = $query->select('users.id')->distinct();

        return DB::query()->fromSub($users, 'filtered_users')
            ->join('login_histories', 'login_histories.user_id', '=', 'filtered_users.id')
            ->whereNotNull("login_histories.{$column}")
            ->selectRaw("login_histories.{$column} as label, COUNT(DISTINCT filtered_users.id) as aggregate")
            ->groupBy("login_histories.{$column}")->orderByDesc('aggregate')->limit(10)->get()
            ->map(fn ($row) => ['label' => ucfirst((string) $row->label), 'count' => (int) $row->aggregate])->all();
    }

    private function group(Builder $query, string $column, int $limit): array
    {
        return $query->select([])->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("{$column} as label, COUNT(DISTINCT users.id) as aggregate")
            ->groupBy($column)->orderByDesc('aggregate')->limit($limit)->get()
            ->map(fn ($row) => ['label' => ucfirst((string) $row->label), 'count' => (int) $row->aggregate])->all();
    }

    private function roles(Builder $query): array
    {
        return $query->select([])->join('model_has_roles', function ($join) {
            $join->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', User::class);
        })->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->selectRaw('roles.name as label, COUNT(DISTINCT users.id) as aggregate')
            ->groupBy('roles.name')->orderByDesc('aggregate')->get()
            ->map(fn ($row) => ['label' => ucfirst(str_replace('_', ' ', $row->label)), 'count' => (int) $row->aggregate])->all();
    }

    private function ages(Builder $query): array
    {
        $buckets = ['Under 18' => 0, '18–24' => 0, '25–34' => 0, '35–44' => 0, '45–54' => 0, '55+' => 0, 'Unknown' => 0];
        $query->select('users.id', 'user_profiles.date_of_birth')->distinct()->get()->each(function ($row) use (&$buckets) {
            if (! $row->date_of_birth) {
                $buckets['Unknown']++;

                return;
            }
            $age = Carbon::parse($row->date_of_birth)->age;
            $label = $age < 18 ? 'Under 18' : ($age < 25 ? '18–24' : ($age < 35 ? '25–34' : ($age < 45 ? '35–44' : ($age < 55 ? '45–54' : '55+'))));
            $buckets[$label]++;
        });

        return collect($buckets)->map(fn ($count, $label) => ['label' => $label, 'count' => $count])->values()->all();
    }

    private function trend(Builder $query): array
    {
        return $query->select('users.id', 'users.created_at')->distinct()->get()
            ->groupBy(fn ($user) => $user->created_at->format('Y-m'))
            ->sortKeys()->map(fn (Collection $users, string $month) => ['label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'), 'count' => $users->count()])
            ->values()->all();
    }
}
