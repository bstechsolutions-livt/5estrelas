<?php

namespace Tests\Feature;

use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalSenderDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(ApprovalWorkflowService::class);

        ApprovalTrail::create([
            'area' => 'matriz',
            'order' => 1,
            'level_name' => 'departamento',
            'role_label' => 'Departamento',
            'default_user_id' => null,
        ]);
    }

    private function userWithPerm(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro']);
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    private function departmentComGestor(): Department
    {
        $manager = User::factory()->create(['is_active' => true]);
        $dept = Department::create(['name' => 'Compras', 'is_active' => true]);
        $dept->forceFill(['area_key' => 'matriz', 'manager_id' => $manager->id])->save();

        return $dept;
    }

    public function test_preview_bloqueia_usuario_sem_departamento(): void
    {
        $user = $this->userWithPerm(['financeiro.contas_pagar.preparar']);
        $preview = $this->workflow->buildPreviewStepsForSender($user);

        $this->assertFalse($preview['ok']);
        $this->assertStringContainsString('departamento', $preview['errors'][0]);
    }

    public function test_preview_bloqueia_departamento_sem_area_key(): void
    {
        $dept = Department::create(['name' => 'Sem área', 'is_active' => true]);
        $user = $this->userWithPerm(['financeiro.contas_pagar.preparar']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $preview = $this->workflow->buildPreviewStepsForSender($user->fresh());

        $this->assertFalse($preview['ok']);
        $this->assertTrue(collect($preview['errors'])->contains(fn ($e) => str_contains($e, 'área de aprovação')));
    }

    public function test_preview_bloqueia_departamento_sem_gestor(): void
    {
        $dept = Department::create(['name' => 'Sem gestor', 'is_active' => true]);
        $dept->forceFill(['area_key' => 'matriz'])->save();
        $user = $this->userWithPerm(['financeiro.contas_pagar.preparar']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $preview = $this->workflow->buildPreviewStepsForSender($user->fresh());

        $this->assertFalse($preview['ok']);
        $this->assertTrue(collect($preview['errors'])->contains(fn ($e) => str_contains($e, 'aprovador configurado')));
    }

    public function test_preview_ok_com_departamento_e_gestor(): void
    {
        $dept = $this->departmentComGestor();
        $user = $this->userWithPerm(['financeiro.contas_pagar.preparar']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $preview = $this->workflow->buildPreviewStepsForSender($user->fresh());

        $this->assertTrue($preview['ok']);
        $this->assertCount(1, $preview['steps']);
        $this->assertNotNull($preview['steps'][0]['assignee_id']);
    }

    public function test_preview_usa_diretor_do_departamento_na_etapa_diretoria(): void
    {
        $director = User::factory()->create(['is_active' => true, 'name' => 'Diretor Depto']);
        $manager = User::factory()->create(['is_active' => true]);
        $dept = Department::create(['name' => 'Comercial SP', 'is_active' => true]);
        $dept->forceFill([
            'area_key' => 'matriz',
            'manager_id' => $manager->id,
            'director_id' => $director->id,
        ])->save();

        ApprovalTrail::create([
            'area' => 'matriz', 'order' => 2, 'level_name' => 'diretoria',
            'role_label' => 'Diretoria', 'default_user_id' => User::factory()->create()->id,
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $user->forceFill(['department_id' => $dept->id])->save();

        $preview = $this->workflow->buildPreviewStepsForSender($user->fresh());

        $this->assertTrue($preview['ok']);
        $diretoria = collect($preview['steps'])->firstWhere('level_name', 'diretoria');
        $this->assertSame($director->id, $diretoria['assignee_id']);
        $this->assertSame('Diretor Depto', $diretoria['assignee_name']);
    }

    public function test_envio_http_bloqueia_sem_departamento(): void
    {
        $user = $this->userWithPerm(['*']);
        $payable = Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ]);
        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $user->id,
            'name' => 'doc.pdf',
            'path' => 'payables/doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/enviar-aprovacao")
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
