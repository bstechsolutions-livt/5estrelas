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
                ->has('charts_12h')
                ->has('charts_12h.labels', 12)
                ->where('stats.failed_24h', 1)
                ->where('stats.failed_503_or_timeout_24h', 1)
                ->where('current_run.status', PayableSyncRun::STATUS_RUNNING)
            );
    }

    public function test_monitor_aggregates_hourly_charts_last_12h(): void
    {
        $hour = now('America/Sao_Paulo')->startOfHour();

        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_SUCCESS,
            'started_at' => $hour->copy()->addMinutes(5),
            'finished_at' => $hour->copy()->addMinutes(6),
            'inserted_count' => 2,
            'updated_count' => 3,
            'missing_count' => 1,
        ]);

        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_FAILED,
            'started_at' => $hour->copy()->addMinutes(15),
            'finished_at' => $hour->copy()->addMinutes(16),
            'inserted_count' => 0,
            'updated_count' => 1,
            'missing_count' => 0,
            'error_message' => 'timeout',
        ]);

        // Fora da janela de 12h — não deve entrar nos gráficos.
        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_SUCCESS,
            'started_at' => $hour->copy()->subHours(13),
            'finished_at' => $hour->copy()->subHours(13)->addMinute(),
            'inserted_count' => 99,
            'updated_count' => 99,
            'missing_count' => 99,
        ]);

        $this->actingAs($this->userWith(['financeiro.workflows.configurar']))
            ->get('/financeiro/sync-senior')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('charts_12h.labels', 12)
                ->where('charts_12h.sucesso', fn ($rows) => array_sum(collect($rows)->all()) === 1)
                ->where('charts_12h.falha', fn ($rows) => array_sum(collect($rows)->all()) === 1)
                ->where('charts_12h.inserted', fn ($rows) => array_sum(collect($rows)->all()) === 2)
                ->where('charts_12h.updated', fn ($rows) => array_sum(collect($rows)->all()) === 4)
                ->where('charts_12h.missing', fn ($rows) => array_sum(collect($rows)->all()) === 1)
            );
    }
}
