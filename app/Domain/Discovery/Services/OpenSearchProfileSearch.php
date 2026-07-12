<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Contracts\ProfileIndexer;
use App\Domain\Discovery\Contracts\ProfileSearchEngine;
use App\Domain\Discovery\Support\ProfileDocument;
use App\Models\User;
use OpenSearch\Common\Exceptions\Missing404Exception;

class OpenSearchProfileSearch implements ProfileIndexer, ProfileSearchEngine
{
    public function __construct(private OpenSearchManager $manager, private ProfileDocument $documents) {}

    public function search(array $criteria): array
    {
        $filters = [['term' => ['is_public' => true]]];
        $terms = ['role' => 'roles', 'gender' => 'gender', 'country' => 'country', 'province' => 'province', 'city' => 'city', 'locality' => 'locality', 'township' => 'township', 'sport_id' => 'sport_id', 'position_id' => 'position_id', 'available' => 'is_available'];
        foreach ($terms as $input => $field) {
            if (array_key_exists($input, $criteria)) {
                $filters[] = ['term' => [$field => $criteria[$input]]];
            }
        }if (isset($criteria['min_completeness'])) {
            $filters[] = ['range' => ['completeness' => ['gte' => $criteria['min_completeness']]]];
        }if (isset($criteria['min_age']) || isset($criteria['max_age'])) {
            $filters[] = ['range' => ['age' => array_filter(['gte' => $criteria['min_age'] ?? null, 'lte' => $criteria['max_age'] ?? null], fn ($v) => $v !== null)]];
        }if (isset($criteria['club'])) {
            $filters[] = ['match' => ['club' => $criteria['club']]];
        }$must = isset($criteria['q']) ? [['multi_match' => ['query' => $criteria['q'], 'fields' => ['name^5', 'sport^3', 'position^3', 'club^2', 'organisation^2', 'bio'], 'fuzziness' => 'AUTO']]] : [['match_all' => (object) []]];
        $response = $this->manager->client()->search(['index' => config('discovery.index'), 'body' => ['from' => ($criteria['page'] - 1) * $criteria['per_page'], 'size' => $criteria['per_page'], 'query' => ['bool' => ['must' => $must, 'filter' => $filters]], 'sort' => isset($criteria['q']) ? ['_score', ['completeness' => 'desc']] : [['completeness' => 'desc'], ['_score' => 'desc']]]]);
        $ids = collect($response['hits']['hits'] ?? [])->pluck('_id')->map(fn ($id) => (int) $id);
        $models = User::with('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'professionalProfile', 'organisationProfile')->whereIn('id', $ids)->get()->sortBy(fn ($user) => $ids->search($user->id))->values();

        return ['items' => $models, 'total' => (int) ($response['hits']['total']['value'] ?? 0)];
    }

    public function index(User $user): void
    {
        if (! $user->profile?->is_public) {
            $this->delete($user);

            return;
        }$this->manager->client()->index(['index' => config('discovery.index'), 'id' => (string) $user->id, 'body' => $this->documents->make($user), 'refresh' => false]);
    }

    public function delete(User $user): void
    {
        try {
            $this->manager->client()->delete(['index' => config('discovery.index'), 'id' => (string) $user->id]);
        } catch (Missing404Exception) {
        }
    }
}
