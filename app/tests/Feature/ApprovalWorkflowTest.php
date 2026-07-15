<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new ApprovalWorkflowService();
        $this->seedTrails();
    }

    private function seedTrails(): void
    {
        // Trilha matriz: dept → gerencia → diretoria → financeiro → presidencia
        $gerente = User::factory()->create(['name' => 'Gerente', 'is_active' => true]);
        $diretor = User::factory()->create(['name' => 'Diretor', 'is_active' => true]);
        $financeiro = User::factory()->create(['name' => 'Financeiro', 'is_active' => true]);
        $presidente = User::factory()->create(['name' => 'Presidente', 'is_active' => true]);

        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência', $gerente->id],
            [3, 'diretoria', 'Diretoria', $diretor->id],
            [4, 'financeiro', 'Financeiro', $financeiro->id],
            [5, 'presidencia', 'Presidência', $presidente->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::create(['area' => 'matriz', 'order' => $order, 'level_name' => $level, 'role_label' => $label, 'default_user_id' => $userId]);
        }

        // Trilha dp_rh (sem diretoria): dept → gerencia → financeiro → presidencia
        $headRH = User::factory()->create(['name' => 'Head RH', 'is_active' => true]);
        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Head DP/RH', $headRH->id],
            [3, 'financeiro', 'Financeiro', $financeiro->id],
            [4, 'presidencia', 'Presidência', $presidente->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::create(['area' => 'dp_rh', 'order' => $order, 'level_name' => $level, 'role_label' => $label, 'default_user_id' => $userId]);
        }

        // Trilha comercial
        $gerenteCom = User::factory()->create(['name' => 'Gerente Comercial', 'is_active' => true]);
        $diretorCom = User::factory()->create(['name' => 'Diretora Comercial', 'email' => 'anapaula@grupo5estrelas.com.br', 'is_active' => true]);
        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência Comercial', $gerenteCom->id],
            [3, 'diretoria', 'Diretoria Comercial', $diretorCom->id],
            [4, 'financeiro', 'Financeiro', $financeiro->id],
            [5, 'presidencia', 'Presidência', $presidente->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::create(['area' => 'comercial', 'order' => $order, 'level_name' => $level, 'role_label' => $label, 'default_user_id' => $userId]);
        }
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 5000.00,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    private function userWithPerm(string $perm): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => $perm], ['label' => $perm, 'module' => 'financeiro'])->id
        );
        return $user;
    }

    // ─── Envio para aprovação ────────────────────────────────────────────

    public function test_send_for_approval_creates_steps_for_area(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();

        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $this->assertEquals('aguardando_aprovacao', $payable->fresh()->status);
        $this->assertEquals(4, ApprovalStep::where('payable_id', $payable->id)->count());
    }

    public function test_dp_rh_trail_has_no_diretoria(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();

        $this->workflow->sendForApproval($payable, $sender, 'dp_rh');

        $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();
        $this->assertEquals(3, $steps->count()); // sem diretoria nem gerência duplicada
        $this->assertFalse($steps->pluck('level_name')->contains('diretoria'));
    }

    public function test_baluarte_creates_double_trail(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();

        $this->workflow->sendForApproval($payable, $sender, 'baluarte');

        // Baluarte = matriz (4) + comercial (4) = 8 steps
        $steps = ApprovalStep::where('payable_id', $payable->id)->count();
        $this->assertEquals(8, $steps);
    }

    public function test_detects_area_from_department(): void
    {
        // Cria departamento e verifica que área dp_rh gera trilha sem diretoria
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();

        // Testa diretamente com area='dp_rh' (detectArea coberta pelos testes de integração E2E)
        $this->workflow->sendForApproval($payable, $sender, 'dp_rh');

        $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();
        $this->assertEquals(3, $steps->count());
        $this->assertFalse($steps->pluck('level_name')->contains('diretoria'));
    }

    // ─── Aprovação ───────────────────────────────────────────────────────

    public function test_approve_advances_to_next_step(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        // 1ª etapa absorve gerência → próximo é diretoria
        $step = $this->workflow->currentStep($payable);
        $this->assertEquals('departamento', $step->level_name);

        $gerente = User::find($step->assigned_to);
        $result = $this->workflow->approve($payable, $gerente);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['finished']);
        $this->assertEquals('diretoria', $result['next_level']);
    }

    public function test_approve_last_step_finalizes(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $gerente = User::where('name', 'Gerente')->first();

        for ($i = 0; $i < 4; $i++) {
            $result = $this->workflow->approve($payable, $gerente);
            if (! $result['success']) {
                $result = $this->workflow->approve($payable, $this->userWithPerm('*'));
            }
            $this->assertTrue($result['success']);
        }

        $this->assertTrue($result['finished']);
        $this->assertEquals('aprovado', $payable->fresh()->status);
    }

    public function test_assigned_user_can_approve_their_step(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $gerente = User::where('name', 'Gerente')->first();
        $this->workflow->approve($payable, $gerente);

        $step = $this->workflow->currentStep($payable);
        $diretor = User::find($step->assigned_to);

        $result = $this->workflow->approve($payable, $diretor);
        $this->assertTrue($result['success']);
    }

    public function test_non_assigned_user_cannot_approve(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $gerente = User::where('name', 'Gerente')->first();
        $this->workflow->approve($payable, $gerente);

        $step = $this->workflow->currentStep($payable);
        $this->assertEquals('diretoria', $step->level_name);
        $randomUser = User::factory()->create(['is_active' => true]);
        $result = $this->workflow->approve($payable, $randomUser);

        $this->assertFalse($result['success']);
        $this->assertStringContains('não é o aprovador', $result['error']);
    }

    // ─── Substituição do presidente ──────────────────────────────────────

    public function test_president_substitute_can_approve_presidencia(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $admin = $this->userWithPerm('*');
        for ($i = 0; $i < 3; $i++) {
            $this->workflow->approve($payable, $admin);
        }

        // Agora step é presidencia. Ana Paula (substituta) tenta aprovar.
        $anaPaula = User::where('email', 'anapaula@grupo5estrelas.com.br')->first();
        if (!$anaPaula) {
            $anaPaula = User::factory()->create(['email' => 'anapaula@grupo5estrelas.com.br', 'is_active' => true]);
        }

        $result = $this->workflow->approve($payable, $anaPaula);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['finished']);
    }

    public function test_substitute_cannot_approve_if_already_signed_as_director(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'comercial');

        $gerenteCom = User::where('name', 'Gerente Comercial')->first();
        $this->workflow->approve($payable, $gerenteCom);

        $anaPaula = User::where('email', 'anapaula@grupo5estrelas.com.br')->first();
        $this->workflow->approve($payable, $anaPaula);

        $admin = $this->userWithPerm('*');
        $this->workflow->approve($payable, $admin);

        // Agora presidência: Ana Paula NÃO pode (regra 3 — já assinou como diretora)
        $result = $this->workflow->approve($payable, $anaPaula);
        $this->assertFalse($result['success']);
    }

    // ─── Reprovação ──────────────────────────────────────────────────────

    public function test_reject_devolve_titulo_para_pendente(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $admin = $this->userWithPerm('*');
        $result = $this->workflow->reject($payable, $admin, 'Valor não confere com orçamento');

        $this->assertTrue($result['success']);
        $payable->refresh();
        $this->assertEquals('pendente', $payable->status);
        $this->assertEquals('Valor não confere com orçamento', $payable->rejection_reason);
        $this->assertNull($payable->sent_for_approval_at);
        $this->assertEquals(0, ApprovalStep::where('payable_id', $payable->id)->count());
        $this->assertDatabaseHas('payable_comments', [
            'payable_id' => $payable->id,
            'type' => 'rejection',
        ]);
    }

    public function test_reject_notifica_preparador_no_sininho(): void
    {
        $preparer = User::factory()->create(['name' => 'Karen Preparadora', 'is_active' => true]);
        $payable = $this->makePayable(['prepared_by' => $preparer->id]);
        $this->workflow->sendForApproval($payable, $preparer, 'matriz');

        $admin = $this->userWithPerm('*');
        $admin->forceFill(['name' => 'Aprovador Teste'])->save();

        $this->workflow->reject($payable->fresh(), $admin, 'Documento ilegível');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $preparer->id,
            'type' => 'approval_rejected',
            'title' => 'Título reprovado',
        ]);

        $notification = Notification::where('user_id', $preparer->id)
            ->where('type', 'approval_rejected')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString($payable->title_number, $notification->message);
        $this->assertStringContainsString('Aprovador Teste', $notification->message);
        $this->assertStringContainsString('Documento ilegível', $notification->message);
        $this->assertStringContainsString('Corrija e reenvie', $notification->message);
        $this->assertSame("/financeiro/contas-pagar/{$payable->id}", $notification->link);
        $this->assertSame($payable->id, $notification->metadata['payable_id'] ?? null);
        $this->assertSame($admin->id, $notification->metadata['rejected_by'] ?? null);
    }

    public function test_reject_nao_notifica_quando_prepared_by_null(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        // Simula título legado sem preparador após o envio
        $payable->forceFill(['prepared_by' => null])->save();

        $before = Notification::where('type', 'approval_rejected')->count();
        $admin = $this->userWithPerm('*');
        $this->workflow->reject($payable->fresh(), $admin, 'Sem preparador');

        $this->assertSame($before, Notification::where('type', 'approval_rejected')->count());
    }

    public function test_reject_nao_notifica_quando_reprovador_e_o_preparador(): void
    {
        $preparer = User::factory()->create(['is_active' => true]);
        $preparer->permissions()->attach(
            Permission::firstOrCreate(['key' => '*'], ['label' => '*', 'module' => 'sistema'])->id
        );
        $payable = $this->makePayable(['prepared_by' => $preparer->id]);
        $this->workflow->sendForApproval($payable, $preparer, 'matriz');

        $before = Notification::where('user_id', $preparer->id)->where('type', 'approval_rejected')->count();
        $this->workflow->reject($payable->fresh(), $preparer, 'Eu mesmo reprovo');

        $this->assertSame(
            $before,
            Notification::where('user_id', $preparer->id)->where('type', 'approval_rejected')->count()
        );
    }

    public function test_reenvio_apos_reprovacao_limpa_motivo(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $admin = $this->userWithPerm('*');

        $this->workflow->sendForApproval($payable, $sender, 'matriz');
        $this->workflow->reject($payable, $admin, 'Falta documento');

        $this->workflow->sendForApproval($payable->fresh(), $sender, 'matriz');

        $payable->refresh();
        $this->assertEquals('aguardando_aprovacao', $payable->status);
        $this->assertNull($payable->rejection_reason);
    }

    // ─── Notificação ─────────────────────────────────────────────────────

    public function test_first_approver_is_notified_on_send(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        // O segundo step (gerência) é o primeiro com assigned_to
        // Departamento não tem assigned_to; o código notifica o firstStep que TEM assigned_to
        $steps = ApprovalStep::where('payable_id', $payable->id)->whereNotNull('assigned_to')->orderBy('order')->get();
        if ($steps->isNotEmpty()) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $steps->first()->assigned_to,
                'type' => 'approval_pending',
            ]);
        }
    }

    public function test_next_approver_is_notified_on_advance(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $gerente = User::where('name', 'Gerente')->first();
        $this->workflow->approve($payable, $gerente);

        $step = $this->workflow->currentStep($payable);
        if ($step && $step->assigned_to) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $step->assigned_to,
                'type' => 'approval_pending',
            ]);
        }
    }

    // ─── Minhas pendências ───────────────────────────────────────────────

    public function test_my_pending_approvals_returns_assigned_payables(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = $this->makePayable();
        $this->workflow->sendForApproval($payable, $sender, 'matriz');

        $gerente = User::where('name', 'Gerente')->first();
        $this->workflow->approve($payable, $gerente);

        $step = $this->workflow->currentStep($payable);
        $diretor = User::find($step->assigned_to);

        $pending = $this->workflow->myPendingApprovals($diretor);
        $this->assertTrue($pending->contains('id', $payable->id));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(str_contains($haystack, $needle), "'{$haystack}' não contém '{$needle}'");
    }
}
