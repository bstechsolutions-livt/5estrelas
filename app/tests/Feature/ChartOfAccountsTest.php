<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\Receivable;
use App\Models\User;
use App\Services\Senior\ChartOfAccountsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    public function test_sync_deriva_contas_de_payables(): void
    {
        Payable::create([
            'title_number' => 'T1',
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'codemp' => 2,
            'ctafin' => 108020,
            'codccu' => '2363',
        ]);

        $result = (new ChartOfAccountsSyncService())->run();

        $this->assertGreaterThanOrEqual(2, $result['total_distinct']);
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '108020',
            'account_type' => ChartOfAccount::TYPE_CONTA_FINANCEIRA,
            'codemp' => 2,
        ]);
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '2363',
            'account_type' => ChartOfAccount::TYPE_CENTRO_CUSTO,
            'codemp' => 2,
        ]);
    }

    public function test_plano_de_contas_index(): void
    {
        ChartOfAccount::create([
            'code' => '102040',
            'account_type' => ChartOfAccount::TYPE_CONTA_FINANCEIRA,
            'codemp' => 3,
            'source' => 'derived',
        ]);

        $this->actingAs($this->userWith('financeiro.plano_contas.visualizar'))
            ->get('/financeiro/plano-de-contas')
            ->assertOk();
    }

    public function test_contas_receber_index_e_show(): void
    {
        $receivable = Receivable::create([
            'title_number' => '2705_01',
            'customer_name' => 'Cliente 10',
            'amount' => 6017.19,
            'open_amount' => 6017.19,
            'due_date' => '2026-07-12',
            'senior_id' => '3-1-2705_01-NFS-10',
            'senior_situacao_original' => 'AB',
            'codemp' => 3,
            'codfil' => 1,
            'codcli' => 10,
        ]);

        $user = $this->userWith('financeiro.contas_receber.visualizar', 'financeiro.contas_receber.ver_todas_filiais');

        $this->actingAs($user)->get('/financeiro/contas-receber')->assertOk();
        $this->actingAs($user)->get("/financeiro/contas-receber/{$receivable->id}")->assertOk();
    }
}
