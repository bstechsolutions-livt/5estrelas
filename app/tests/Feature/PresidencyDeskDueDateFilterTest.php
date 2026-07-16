<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PresidencyDeskDueDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function president(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (['financeiro.presidencia.painel', 'financeiro.contas_pagar.ver_todas_filiais'] as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['key' => $key],
                    ['label' => $key, 'module' => 'financeiro']
                )->id
            );
        }

        return $user;
    }

    private function payableAtPresidency(User $president, string $dueDate, string $title): Payable
    {
        $payable = Payable::create([
            'title_number' => $title,
            'supplier_name' => 'Fornecedor',
            'amount' => 1000,
            'due_date' => $dueDate,
            'status' => 'aguardando_aprovacao',
            'sent_for_approval_at' => now(),
        ]);

        ApprovalStep::create([
            'payable_id' => $payable->id,
            'order' => 1,
            'level_name' => 'presidencia',
            'role_label' => 'Presidência',
            'approver_type' => 'usuario',
            'status' => 'pendente',
            'assigned_to' => $president->id,
        ]);

        return $payable;
    }

    public function test_filters_presidency_desk_by_due_date_range(): void
    {
        $president = $this->president();

        $today = $this->payableAtPresidency($president, '2026-07-15', 'TIT-HOJE');
        $nextWeek = $this->payableAtPresidency($president, '2026-07-22', 'TIT-SEMANA');
        $this->payableAtPresidency($president, '2026-08-10', 'TIT-MES');

        $this->actingAs($president)
            ->get('/financeiro/presidencia?due_from=2026-07-15&due_to=2026-07-22')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Approvals/PresidencyDesk', false)
                ->has('payables', 2)
                ->where('filters.due_from', '2026-07-15')
                ->where('filters.due_to', '2026-07-22')
                ->where('payables.0.id', $today->id)
                ->where('payables.1.id', $nextWeek->id)
            );
    }

    public function test_presidency_desk_defaults_to_due_this_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00')); // quarta

        $president = $this->president();

        $inWeek = $this->payableAtPresidency($president, '2026-07-17', 'TIT-SEMANA'); // sex
        $this->payableAtPresidency($president, '2026-07-22', 'TIT-FORA'); // quarta seguinte

        // Sem query → default av_semana: 2026-07-15 → 2026-07-19 (domingo)
        $this->actingAs($president)
            ->get('/financeiro/presidencia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Approvals/PresidencyDesk', false)
                ->has('payables', 1)
                ->where('filters.due_from', '2026-07-15')
                ->where('filters.due_to', '2026-07-19')
                ->where('payables.0.id', $inWeek->id)
            );

        Carbon::setTestNow();
    }

    public function test_presidency_desk_all_returns_unfiltered(): void
    {
        $president = $this->president();

        $this->payableAtPresidency($president, '2026-07-15', 'TIT-A');
        $this->payableAtPresidency($president, '2026-08-10', 'TIT-B');

        $this->actingAs($president)
            ->get('/financeiro/presidencia?all=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Approvals/PresidencyDesk', false)
                ->has('payables', 2)
                ->where('pendingCount', 2)
                ->where('filters.all', true)
            );
    }
}
