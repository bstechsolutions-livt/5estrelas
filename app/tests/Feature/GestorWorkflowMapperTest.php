<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\GestorWorkflowMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestorWorkflowMapperTest extends TestCase
{
    use RefreshDatabase;

    private GestorWorkflowMapper $mapper;

    private User $preparer;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTrails();
        $this->mapper = app(GestorWorkflowMapper::class);
        $this->mapper->setUserResolver(fn (?string $id) => $id === 'gestor-preparer' ? $this->preparer->id : null);

        $this->department = Department::create([
            'name' => 'Operações Matriz',
            'slug' => 'operacoes-matriz',
            'is_active' => true,
            'area_key' => 'matriz',
        ]);

        $this->preparer = User::factory()->create([
            'is_active' => true,
            'department_id' => $this->department->id,
        ]);
    }

    private function seedTrails(): void
    {
        $gerente = User::factory()->create(['name' => 'Gerente', 'is_active' => true]);
        $diretor = User::factory()->create(['name' => 'Diretor', 'is_active' => true]);
        $financeiro = User::factory()->create(['name' => 'Financeiro', 'is_active' => true]);
        $presidente = User::factory()->create(['name' => 'Leonardo', 'is_active' => true]);

        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência', $gerente->id],
            [3, 'diretoria', 'Diretoria', $diretor->id],
            [4, 'financeiro', 'Financeiro', $financeiro->id],
            [5, 'presidencia', 'Presidência', $presidente->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::create([
                'area' => 'matriz',
                'order' => $order,
                'level_name' => $level,
                'role_label' => $label,
                'default_user_id' => $userId,
            ]);
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
            'department_id' => $this->department->id,
            'prepared_by' => $this->preparer->id,
        ], $attrs));
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function history(array $events): array
    {
        $base = 1_700_000_000_000;
        $out = [];
        foreach ($events as $i => $event) {
            $out[] = array_merge([
                'at' => $base + ($i * 60_000),
                'by' => 'gestor-preparer',
            ], $event);
        }

        return $out;
    }

    public function test_resolve_gestor_phase_from_status_map(): void
    {
        $this->assertSame('analysis', $this->mapper->resolveGestorPhase('awaiting-analysis', []));
        $this->assertSame('final', $this->mapper->resolveGestorPhase('awaiting-approval', []));
        $this->assertSame('department', $this->mapper->resolveGestorPhase('awaiting-department-approval', []));
        $this->assertNull($this->mapper->resolveGestorPhase('awaiting-receipt', []));
    }

    public function test_infer_department_phase_from_history_when_not_in_phase_map(): void
    {
        config(['gestor_migration.gestor_workflow_phase' => [
            'awaiting-approval' => 'final',
        ]]);

        $history = $this->history([
            ['type' => 'sent-to-department-approval'],
        ]);

        $this->assertSame('department', $this->mapper->resolveGestorPhase('awaiting-analysis', $history));
    }

    public function test_build_workflow_update_maps_rejection_reasons(): void
    {
        $rectification = $this->mapper->buildWorkflowUpdate([
            'status' => 'awaiting-rectification',
            'rectificationReason' => 'Falta NF',
            'history' => [],
        ]);
        $this->assertSame('pendente', $rectification['status']);
        $this->assertSame('Falta NF', $rectification['rejection_reason']);

        $release = $this->mapper->buildWorkflowUpdate([
            'status' => 'awaiting-release',
            'retentionReason' => 'Aguardando contrato',
            'history' => [],
        ]);
        $this->assertSame('pendente', $release['status']);
        $this->assertSame('Aguardando contrato', $release['rejection_reason']);

        $inclusion = $this->mapper->buildWorkflowUpdate([
            'status' => 'awaiting-inclusion',
            'history' => $this->history([
                ['type' => 'approved'],
                ['type' => 'paid'],
            ]),
        ]);
        $this->assertSame('pago', $inclusion['status']);
        $this->assertNotNull($inclusion['paid_at']);
        $this->assertNull($inclusion['conciliated_at']);
    }

    public function test_apply_workflow_position_analysis_phase(): void
    {
        $payable = $this->makePayable(['status' => 'aguardando_aprovacao']);
        $history = $this->history([
            ['type' => 'sent-to-department-approval'],
            ['type' => 'sent-to-analysis'],
        ]);

        $result = $this->mapper->applyWorkflowPosition($payable, 'awaiting-analysis', $history);

        $this->assertTrue($result['ok']);
        $this->assertSame('analysis', $result['phase']);
        $this->assertSame('financeiro', $result['target_level']);

        $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();
        $this->assertGreaterThan(1, $steps->count());

        $current = $steps->firstWhere('status', 'pendente');
        $this->assertSame('financeiro', $current?->level_name);
        $this->assertTrue($steps->where('order', '<', $current->order)->every(fn ($s) => $s->status === 'aprovado'));
    }

    public function test_apply_workflow_position_final_phase(): void
    {
        $payable = $this->makePayable(['status' => 'aguardando_aprovacao']);
        $history = $this->history([
            ['type' => 'sent-to-analysis'],
            ['type' => 'sent-to-approval'],
        ]);

        $result = $this->mapper->applyWorkflowPosition($payable, 'awaiting-approval', $history);

        $this->assertTrue($result['ok']);
        $this->assertSame('final', $result['phase']);
        $this->assertSame('presidencia', $result['target_level']);

        $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();
        $current = $steps->firstWhere('status', 'pendente');
        $this->assertSame('presidencia', $current?->level_name);
        $this->assertTrue($steps->where('order', '<', $current->order)->every(fn ($s) => $s->status === 'aprovado'));
    }

    public function test_apply_workflow_position_clears_steps_for_rejection_status(): void
    {
        $payable = $this->makePayable(['status' => 'aguardando_aprovacao']);
        app(ApprovalWorkflowService::class)->sendForApproval($payable, $this->preparer, 'matriz');
        $this->assertGreaterThan(0, ApprovalStep::where('payable_id', $payable->id)->count());

        $result = $this->mapper->applyWorkflowPosition($payable, 'awaiting-reanalysis', [
            ['type' => 'sent-to-reanalysis', 'at' => 1_700_000_000_000, 'by' => 'gestor-preparer'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['cleared']);
        $this->assertSame(0, ApprovalStep::where('payable_id', $payable->id)->count());
    }

    public function test_apply_workflow_position_department_phase(): void
    {
        $payable = $this->makePayable(['status' => 'aguardando_aprovacao']);
        $history = $this->history([
            ['type' => 'sent-to-department-approval'],
        ]);

        $result = $this->mapper->applyWorkflowPosition($payable, 'awaiting-department-approval', $history);

        $this->assertTrue($result['ok']);
        $this->assertSame('department', $result['phase']);

        $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();
        $current = $steps->firstWhere('status', 'pendente');
        $this->assertSame(1, $current?->order);
        $this->assertSame(0, $steps->where('status', 'aprovado')->count());
    }
}
