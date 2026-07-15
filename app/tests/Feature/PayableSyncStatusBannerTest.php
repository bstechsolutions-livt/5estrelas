<?php

namespace Tests\Feature;

use App\Models\PayableSyncRun;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PayableSyncStatusBannerTest extends TestCase
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

    public function test_index_exposes_online_sync_status_from_last_success(): void
    {
        config(['senior.sync_interval_minutes' => 5]);

        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_FAILED,
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(19),
            'error_message' => 'HTTP 503',
        ]);

        $successFinished = now()->subMinutes(3);
        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_SUCCESS,
            'started_at' => $successFinished->copy()->subMinute(),
            'finished_at' => $successFinished,
            'inserted_count' => 1,
            'updated_count' => 0,
            'missing_count' => 0,
        ]);

        $user = $this->userWith([
            'financeiro.contas_pagar.visualizar',
            'financeiro.workflows.configurar',
        ]);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Payables/Index', false)
                ->where('syncStatus.online', true)
                ->where('syncStatus.status_label', 'Online')
                ->where('syncStatus.sync_interval_minutes', 5)
                ->where('syncStatus.can_view_monitor', true)
                ->where('syncStatus.last_finished_at', fn ($v) => is_string($v) && $v !== '')
                ->where('syncStatus.next_estimated_at', fn ($v) => is_string($v) && $v !== '')
            );
    }

    public function test_index_marks_offline_after_last_failure(): void
    {
        PayableSyncRun::create([
            'environment' => 'PRD',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_SCHEDULED,
            'status' => PayableSyncRun::STATUS_FAILED,
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
            'error_message' => 'Senior 503 Unavailable',
        ]);

        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Payables/Index', false)
                ->where('syncStatus.online', false)
                ->where('syncStatus.status_label', 'Offline')
                ->where('syncStatus.can_view_monitor', false)
            );
    }
}
