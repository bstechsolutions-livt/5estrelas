<?php

namespace Tests\Feature;

use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use App\Support\PayableApprovalDeadline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Trava de 72h no envio para aprovação (não no vencimento em si).
 */
class PayableApprovalDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedApprovalTrail();
    }

    private function seedApprovalTrail(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $dept = Department::create(['name' => 'Compras Teste', 'is_active' => true]);
        $dept->forceFill(['area_key' => 'matriz', 'manager_id' => $manager->id])->save();

        ApprovalTrail::create([
            'area' => 'matriz',
            'order' => 1,
            'level_name' => 'departamento',
            'role_label' => 'Departamento',
            'default_user_id' => null,
        ]);
    }

    private function userWithPerms(array $keys): User
    {
        $dept = Department::first();
        $user = User::factory()->create([
            'is_active' => true,
            'department_id' => $dept->id,
        ]);

        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    private function payableWithDoc(User $user, string $dueDate): Payable
    {
        $payable = Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 500,
            'due_date' => $dueDate,
            'status' => 'pendente',
        ]);

        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $user->id,
            'name' => 'nf.pdf',
            'path' => 'payables/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        return $payable;
    }

    public function test_min_due_date_para_aprovacao_hoje_mais_3_dias(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13'));
        $this->assertSame('2026-07-16', PayableApprovalDeadline::minDueDateForApproval()->toDateString());
        Carbon::setTestNow();
    }

    public function test_bloqueia_envio_com_vencimento_antes_de_72h(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13'));
        $user = $this->userWithPerms(['*', 'financeiro.contas_pagar.preparar']);
        $payable = $this->payableWithDoc($user, '2026-07-15');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/enviar-aprovacao")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pendente', $payable->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_permite_envio_com_vencimento_dentro_do_prazo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13'));
        $user = $this->userWithPerms(['*', 'financeiro.contas_pagar.preparar']);
        $payable = $this->payableWithDoc($user, '2026-07-16');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/enviar-aprovacao")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('aguardando_aprovacao', $payable->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_financeiro_pode_enviar_urgente_fora_do_prazo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13'));
        $user = $this->userWithPerms([
            '*',
            'financeiro.contas_pagar.preparar',
            PayableApprovalDeadline::PERMISSION_BYPASS,
        ]);
        $payable = $this->payableWithDoc($user, '2026-07-14');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/enviar-aprovacao", ['urgente' => true])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('aguardando_aprovacao', $payable->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.enviado_aprovacao_urgente']);
        Carbon::setTestNow();
    }

    public function test_sem_permissao_urgente_nao_libera_fora_do_prazo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13'));
        $user = $this->userWithPerms([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.preparar',
            'financeiro.contas_pagar.ver_todas_filiais',
            'financeiro.contas_pagar.ver_todos_departamentos',
        ]);
        $payable = $this->payableWithDoc($user, '2026-07-14');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/enviar-aprovacao", ['urgente' => true])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pendente', $payable->fresh()->status);
        Carbon::setTestNow();
    }
}
