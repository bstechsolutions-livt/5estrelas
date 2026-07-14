<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserRepresentative;
use App\Services\ApprovalWorkflowService;
use App\Services\UserRepresentativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRepresentativeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => '*'],
                ['label' => 'Admin', 'module' => 'sistema'],
            )->id,
        );

        return $user;
    }

    public function test_can_save_representative_on_user_edit(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['name' => 'Silene', 'is_active' => true]);
        $rep = User::factory()->create(['name' => 'Fulano', 'is_active' => true]);

        $this->actingAs($admin)
            ->put("/usuarios/{$owner->id}", [
                'name' => $owner->name,
                'email' => $owner->email,
                'is_active' => true,
                'branch_ids' => [],
                'representatives' => [[
                    'representative_id' => $rep->id,
                    'starts_at' => now()->toDateString(),
                    'ends_at' => now()->addDays(10)->toDateString(),
                    'reason' => 'Férias',
                    'scopes' => [UserRepresentative::SCOPE_FINANCEIRO_APROVACAO],
                    'is_active' => true,
                ]],
            ])
            ->assertRedirect('/usuarios');

        $this->assertDatabaseHas('user_representatives', [
            'user_id' => $owner->id,
            'representative_id' => $rep->id,
            'reason' => 'Férias',
        ]);
    }

    public function test_representative_can_approve_assigned_step_during_window(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $rep = User::factory()->create(['is_active' => true]);
        $rep->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Ver CP', 'module' => 'financeiro'],
            )->id,
        );

        UserRepresentative::create([
            'user_id' => $owner->id,
            'representative_id' => $rep->id,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(5)->toDateString(),
            'scopes' => [UserRepresentative::SCOPE_FINANCEIRO_APROVACAO],
            'is_active' => true,
        ]);

        $dept = Department::create(['name' => 'Financeiro', 'slug' => 'fin_rep', 'area_key' => 'matriz', 'is_active' => true]);
        $payable = Payable::create([
            'title_number' => 'REP-1',
            'supplier_name' => 'X',
            'amount' => 100,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'aguardando_aprovacao',
            'department_id' => $dept->id,
            'prepared_by' => $owner->id,
        ]);

        ApprovalStep::create([
            'payable_id' => $payable->id,
            'order' => 1,
            'level_name' => 'gerencia',
            'role_label' => 'Gerência',
            'assigned_to' => $owner->id,
            'status' => 'pendente',
        ]);

        $workflow = app(ApprovalWorkflowService::class);
        $this->assertTrue($workflow->canUserApprove($payable, $rep));
        $this->assertTrue($workflow->myPendingApprovals($rep)->contains('id', $payable->id));
    }

    public function test_representative_outside_window_cannot_approve(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $rep = User::factory()->create(['is_active' => true]);

        UserRepresentative::create([
            'user_id' => $owner->id,
            'representative_id' => $rep->id,
            'starts_at' => now()->subDays(20)->toDateString(),
            'ends_at' => now()->subDays(5)->toDateString(),
            'scopes' => [UserRepresentative::SCOPE_FINANCEIRO_APROVACAO],
            'is_active' => true,
        ]);

        $payable = Payable::create([
            'title_number' => 'REP-OUT',
            'supplier_name' => 'X',
            'amount' => 100,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'aguardando_aprovacao',
        ]);

        ApprovalStep::create([
            'payable_id' => $payable->id,
            'order' => 1,
            'level_name' => 'gerencia',
            'assigned_to' => $owner->id,
            'status' => 'pendente',
        ]);

        $this->assertFalse(app(ApprovalWorkflowService::class)->canUserApprove($payable, $rep));
        $this->assertFalse(app(UserRepresentativeService::class)->isActiveRepresentative($rep, $owner));
    }
}
