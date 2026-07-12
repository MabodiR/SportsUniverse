<?php

namespace App\Domain\Discovery\Services;

use OpenSearch\Client;
use OpenSearch\GuzzleClientFactory;

class OpenSearchManager
{
    public function client(): Client
    {
        $options = ['base_uri' => config('discovery.hosts')[0], 'verify' => config('discovery.verify_ssl')];
        if (config('discovery.username')) {
            $options['auth'] = [config('discovery.username'), config('discovery.password')];
        }

return (new GuzzleClientFactory(maxRetries: 2))->create($options);
    }

    public function ensureIndex(): void
    {
        $client = $this->client();
        $index = config('discovery.index');
        if ($client->indices()->exists(['index' => $index])) {
            return;
        }$client->indices()->create(['index' => $index, 'body' => ['settings' => ['index' => ['number_of_shards' => 3, 'number_of_replicas' => 1]], 'mappings' => ['properties' => ['name' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]], 'name_keyword' => ['type' => 'keyword'], 'slug' => ['type' => 'keyword'], 'roles' => ['type' => 'keyword'], 'bio' => ['type' => 'text'], 'date_of_birth' => ['type' => 'date'], 'age' => ['type' => 'integer'], 'gender' => ['type' => 'keyword'], 'country' => ['type' => 'keyword'], 'province' => ['type' => 'keyword'], 'city' => ['type' => 'keyword'], 'locality' => ['type' => 'keyword'], 'township' => ['type' => 'keyword'], 'is_available' => ['type' => 'boolean'], 'is_public' => ['type' => 'boolean'], 'completeness' => ['type' => 'integer'], 'sport_id' => ['type' => 'long'], 'sport' => ['type' => 'keyword'], 'position_id' => ['type' => 'long'], 'position' => ['type' => 'keyword'], 'club' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]], 'playing_level' => ['type' => 'keyword'], 'professional_type' => ['type' => 'keyword'], 'organisation' => ['type' => 'text'], 'updated_at' => ['type' => 'date']]]]]);
    }
}
