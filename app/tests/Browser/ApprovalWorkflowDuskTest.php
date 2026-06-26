<?php

namespace Tests\Browser;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ApprovalWorkflowDuskTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function setupTrail(): void
    {
        if (ApprovalTrail::where('area', 'matriz')->exists()) return;
        $bruno = $this->bruno();
        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência', $bruno->id],
            [3, 'diretoria', 'Diretoria', $bruno->id],
            [4, 'financeiro', 'Financeiro', $bruno->id],
            [5, 'presidencia', 'Presidência', $bruno->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::firstOrCreate(
                ['area' => 'matriz', 'order' => $order],
                ['level_name' => $level, 'role_label' => $label, 'default_user_id' => $userId]
            );
        }
    }

    private function makePayable(string $status = 'pendente'): Payable
    {
        return Payable::create([
            'title_number' => 'DUSK-WF-' . uniqid(),
            'supplier_name' => 'Fornecedor Dusk Workflow',
            'amount' => 2500.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => $status,
        ]);
    }

    public function test_minhas_pendencias_carrega(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/pendencias')
                ->waitForText('Pendências', 10)
                ->assertSee('Aprovação');
        });
    }

    public function test_configurar_fluxos_carrega(): void
    {
        $this->setupTrail();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/fluxos-aprovacao')
                ->waitForText('Configurar Fluxos', 10)
                ->assertSee('Salvar');
        });
    }

    public function test_stepper_aparece_com_steps(): void
    {
        $this->setupTrail();
        $p = $this->makePayable('pendente');
        $workflow = new ApprovalWorkflowService();
        $workflow->sendForApproval($p, $this->bruno(), 'matriz');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fluxo de Aprovação', 10)
                ->assertSee('departamento')
                ->assertSee('Aprovar');
        });

        \App\Models\ApprovalStep::where("payable_id", $p->id)->delete();
        $p->delete();
    }

    public function test_aprovar_avanca_step(): void
    {
        $this->setupTrail();
        $p = $this->makePayable('pendente');
        $workflow = new ApprovalWorkflowService();
        $workflow->sendForApproval($p, $this->bruno(), 'matriz');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Aprovar', 10)
                ->press('Aprovar')
                ->waitForText('Aprovado na etapa', 10);
        });

        $this->assertGreaterThanOrEqual(1, ApprovalStep::where('payable_id', $p->id)->where('status', 'aprovado')->count());
        \App\Models\ApprovalStep::where("payable_id", $p->id)->delete();
        $p->delete();
    }

    public function skip_test_segunda_assinatura_encerra(): void
    {
        PayableRole::firstOrCreate(['role' => 'assinante', 'user_id' => $this->bruno()->id]);
        $p = $this->makePayable('conciliado');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText("Encerrar ciclo", 20)
                ->press('Encerrar ciclo')
                ->pause(2000);
        });

        $this->assertDatabaseHas('payables', ['id' => $p->id, 'status' => 'encerrado']);
        $p->delete();
    }

    public function test_mention_textarea_existe(): void
    {
        $p = $this->makePayable('pendente');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Workflow', 10)
                ->assertPresent('textarea');
        });

        $p->delete();
    }
}
