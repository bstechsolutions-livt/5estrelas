<?php

namespace Tests\Feature;

use App\Models\BankStatementImport;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test: todas as rotas do financeiro retornam 200 (sem tela branca/500).
 */
class FinanceiroRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => '*'], ['label' => '*', 'module' => 'system'])->id
        );
        return $user;
    }

    public function test_contas_pagar_index(): void
    {
        $this->actingAs($this->admin())->get('/financeiro/contas-pagar')->assertOk();
    }

    public function test_contas_pagar_lote(): void
    {
        $this->actingAs($this->admin())->get('/financeiro/contas-pagar/lote')->assertOk();
    }

    public function test_contas_pagar_show(): void
    {
        $p = Payable::create(['title_number' => 'T1', 'supplier_name' => 'F', 'amount' => 100, 'due_date' => now()->toDateString(), 'status' => 'pendente']);
        $this->actingAs($this->admin())->get("/financeiro/contas-pagar/{$p->id}")->assertOk();
    }

    public function test_alcada(): void
    {
        $user = $this->admin();
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => 'financeiro.contas_pagar.alcada_gerenciar'], ['label' => 'x', 'module' => 'financeiro'])->id
        );
        $this->actingAs($user)->get('/financeiro/contas-pagar/alcada')->assertOk();
    }

    public function test_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/financeiro/dashboard')->assertOk();
    }

    public function test_pendencias(): void
    {
        $this->actingAs($this->admin())->get('/financeiro/pendencias')->assertOk();
    }

    public function test_fluxos_aprovacao(): void
    {
        $user = $this->admin();
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => 'financeiro.workflows.configurar'], ['label' => 'x', 'module' => 'financeiro'])->id
        );
        $this->actingAs($user)->get('/financeiro/fluxos-aprovacao')->assertOk();
    }

    public function test_conciliacao_index(): void
    {
        $this->actingAs($this->admin())->get('/financeiro/contas-pagar/conciliacao')->assertOk();
    }

    public function test_conciliacao_show(): void
    {
        $user = $this->admin();
        $import = BankStatementImport::create([
            'user_id' => $user->id, 'bank_name' => 'BB', 'bank_id' => '001',
            'account_number' => '123', 'file_name' => 'x.ofx', 'file_path' => 'x',
            'status' => 'done', 'transaction_count' => 0, 'matched_count' => 0,
        ]);
        $this->actingAs($user)->get("/financeiro/contas-pagar/conciliacao/{$import->id}")->assertOk();
    }

    public function test_mentionable_users(): void
    {
        $p = Payable::create(['title_number' => 'T2', 'supplier_name' => 'F', 'amount' => 100, 'due_date' => now()->toDateString(), 'status' => 'pendente']);
        $this->actingAs($this->admin())->get("/financeiro/contas-pagar/{$p->id}/mentionable-users")->assertOk();
    }
}
