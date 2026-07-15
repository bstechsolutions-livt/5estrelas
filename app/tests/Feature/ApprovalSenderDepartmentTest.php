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
        ApprovalTrail::create([
            'area' => 'dp_rh',
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

    private function departmentComGestor(string $name = 'Compras', string $area = 'matriz'): Department
    {
        $manager = User::factory()->create(['is_active' => true]);
        $dept = Department::create(['name' => $name, 'is_active' => true]);
        $dept->forceFill(['area_key' => $area, 'manager_id' => $manager->id])->save();

        return $dept;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-'.uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    private function attachDoc(Payable $payable, User $user): void
    {
        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $user->id,
            'name' => 'doc.pdf',
            'path' => 'payables/doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);
    }

    public function test_preview_bloqueia_titulo_sem_departamento(): void
    {
        $payable = $this->makePayable();
        $preview = $this->workflow->buildPreviewStepsForPayable($payable);

        $this->assertFalse($preview['ok']);
        $this->assertStringContainsString('departamento', mb_strtolower($preview['errors'][0]));
    }

    public function test_preview_bloqueia_departamento_sem_area_key(): void
    {
        $dept = Department::create(['name' => 'Sem área', 'is_active' => true]);
        $payable = $this->makePayable(['department_id' => $dept->id]);

        $preview = $this->workflow->buildPreviewStepsForPayable($payable);

        $this->assertFalse($preview['ok']);
        $this->assertTrue(collect($preview['errors'])->contains(fn ($e) => str_contains($e, 'área de aprovação')));
    }

    public function test_preview_bloqueia_departamento_sem_gestor_e_sem_gerencia_na_trilha(): void
    {
        ApprovalTrail::where('area', 'matriz')->where('level_name', 'gerencia')->delete();

        $dept = Department::create(['name' => 'Sem gestor', 'is_active' => true]);
        $dept->forceFill(['area_key' => 'matriz'])->save();
        $payable = $this->makePayable(['department_id' => $dept->id]);

        $preview = $this->workflow->buildPreviewStepsForPayable($payable);

        $this->assertFalse($preview['ok']);
    }

    public function test_preview_ok_com_departamento_e_gestor(): void
    {
        $dept = $this->departmentComGestor();
        $payable = $this->makePayable(['department_id' => $dept->id]);

        $preview = $this->workflow->buildPreviewStepsForPayable($payable);

        $this->assertTrue($preview['ok']);
        $this->assertCount(1, $preview['steps']);
        $this->assertNotNull($preview['steps'][0]['assignee_id']);
        $this->assertSame($dept->id, $preview['department']['id']);
    }

    public function test_preview_usa_departamento_do_titulo_e_nao_do_remetente(): void
    {
        $dp = $this->departmentComGestor('DP / RH', 'dp_rh');
        $modernizacao = $this->departmentComGestor('Modernização', 'matriz');

        $sender = $this->userWithPerm(['financeiro.contas_pagar.preparar']);
        $sender->forceFill(['department_id' => $modernizacao->id])->save();

        $payable = $this->makePayable(['department_id' => $dp->id]);

        $preview = $this->workflow->buildPreviewStepsForPayable($payable);

        $this->assertTrue($preview['ok']);
        $this->assertSame($dp->id, $preview['department']['id']);
        $this->assertSame('DP / RH', $preview['department']['name']);
        $this->assertSame('dp_rh', $preview['area']);
        $this->assertNotSame($modernizacao->id, $preview['department']['id']);
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

        $payable = $this->makePayable(['department_id' => $dept->id]);

        $preview = $this->workflow->buildPreviewStepsForPayable($payable);

        $this->assertTrue($preview['ok']);
        $diretoria = collect($preview['steps'])->firstWhere('level_name', 'diretoria');
        $this->assertSame($director->id, $diretoria['assignee_id']);
        $this->assertSame('Diretor Depto', $diretoria['assignee_name']);
    }

    public function test_envio_usa_departamento_do_titulo_nao_do_remetente(): void
    {
        $dp = $this->departmentComGestor('DP / RH', 'dp_rh');
        $modernizacao = $this->departmentComGestor('Modernização', 'matriz');

        $sender = $this->userWithPerm(['*']);
        $sender->forceFill(['department_id' => $modernizacao->id])->save();

        $payable = $this->makePayable(['department_id' => $dp->id]);
        $this->attachDoc($payable, $sender);

        $this->workflow->sendForApproval($payable, $sender);

        $payable->refresh();
        $this->assertSame($dp->id, (int) $payable->department_id);
        $this->assertSame('aguardando_aprovacao', $payable->status);
    }

    public function test_envio_http_bloqueia_sem_departamento_no_titulo(): void
    {
        $user = $this->userWithPerm(['*']);
        $payable = $this->makePayable();
        $this->attachDoc($payable, $user);

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/enviar-aprovacao")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_legacy_preview_sender_ainda_funciona(): void
    {
        $dept = $this->departmentComGestor();
        $user = $this->userWithPerm(['financeiro.contas_pagar.preparar']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $preview = $this->workflow->buildPreviewStepsForSender($user->fresh());

        $this->assertTrue($preview['ok']);
        $this->assertSame($dept->id, $preview['department']['id']);
    }
}
