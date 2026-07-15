<?php

namespace Tests\Feature;

use App\Models\PayableSyncRun;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PayableSyncMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    public function test_monitor_requires_permission(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);

        $this->actingAs($user)
            ->get('/financeiro/sync-senior')
            ->assertForbidden();
    }

    public function test_monitor_renders_runs_and_503_stats(): void
    {
        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_FAILED,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
            'inserted_count' => 0,
            'updated_count' => 0,
            'missing_count' => 0,
            'error_message' => 'Senior respondeu HTTP 503',
        ]);

        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_RUNNING,
            'started_at' => now()->subMinute(),
            'finished_at' => null,
        ]);

        $this->actingAs($this->userWith(['financeiro.workflows.configurar']))
            ->get('/financeiro/sync-senior')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Financeiro/SyncMonitor/Index', false)
                ->has('runs')
                ->has('config')
                ->where('stats.failed_24h', 1)
                ->where('stats.failed_503_or_timeout_24h', 1)
                ->where('current_run.status', PayableSyncRun::STATUS_RUNNING)
            );
    }
}
