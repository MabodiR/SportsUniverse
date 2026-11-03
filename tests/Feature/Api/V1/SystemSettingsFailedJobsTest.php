<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsFailedJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('fan', 'web');
    }

    public function test_admin_can_view_failed_job_details(): void
    {
        $admin = $this->member('admin');
        $this->failedJob('failed-job-uuid', 'App\\Jobs\\ProcessMedia', 'Video conversion failed.');

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/system-settings')
            ->assertOk()
            ->assertJsonPath('data.health.failed_jobs', 1)
            ->assertJsonPath('data.failed_jobs.0.uuid', 'failed-job-uuid')
            ->assertJsonPath('data.failed_jobs.0.name', 'ProcessMedia')
            ->assertJsonPath('data.failed_jobs.0.error', 'Video conversion failed.');
    }

    public function test_admin_can_retry_an_individual_failed_job(): void
    {
        $admin = $this->member('admin');
        $this->failedJob('retry-job-uuid', 'App\\Jobs\\ProcessMedia', 'Failed.');
        Artisan::shouldReceive('call')->once()->with('queue:retry', [
            'id' => ['retry-job-uuid'],
            '--no-interaction' => true,
        ])->andReturn(0);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/system-settings/failed-jobs/retry-job-uuid/retry')
            ->assertOk()
            ->assertJsonPath('message', 'The failed job was placed back on its queue.');
    }

    public function test_non_admin_cannot_view_or_retry_failed_jobs(): void
    {
        $member = $this->member('fan');
        $this->failedJob('protected-job-uuid', 'App\\Jobs\\ProcessMedia', 'Failed.');

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/admin/system-settings')->assertForbidden();
        $this->postJson('/api/v1/admin/system-settings/failed-jobs/protected-job-uuid/retry')->assertForbidden();
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function failedJob(string $uuid, string $name, string $exception): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'media',
            'payload' => json_encode(['displayName' => $name], JSON_THROW_ON_ERROR),
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }
}
