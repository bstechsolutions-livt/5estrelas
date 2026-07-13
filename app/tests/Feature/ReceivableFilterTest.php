<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableFilterTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_receber.visualizar'],
                ['label' => 'Visualizar CR', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_receber.ver_todas_filiais'],
                ['label' => 'Ver todas filiais CR', 'module' => 'financeiro'],
            )->id,
        );

        return $user;
    }

    private function makeReceivable(array $attrs = []): Receivable
    {
        return Receivable::create(array_merge([
            'title_number' => 'CR-' . uniqid(),
            'customer_name' => 'Cliente Teste',
            'amount' => 500.00,
            'open_amount' => 500.00,
            'due_date' => '2026-06-15',
            'senior_situacao_original' => 'AB',
        ], $attrs));
    }

    public function test_filtro_vencimento_aceita_data_brasileira(): void
    {
        $this->makeReceivable(['customer_name' => 'Dentro', 'due_date' => '2026-06-15']);
        $this->makeReceivable(['customer_name' => 'Fora', 'due_date' => '2026-07-01']);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-receber?due_from=01/06/2026&due_to=30/06/2026')
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('customer_name')->all();

        $this->assertContains('Dentro', $names);
        $this->assertNotContains('Fora', $names);
    }

    public function test_filtro_vencimento_ignora_em_dash_invalido(): void
    {
        $this->makeReceivable(['customer_name' => 'Qualquer', 'due_date' => '2026-06-15']);

        $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-receber?due_from=—&due_to=19/06/2026')
            ->assertOk();
    }

    public function test_filtro_situacao_senior_e_empresa(): void
    {
        $this->makeReceivable([
            'customer_name' => 'Match',
            'codemp' => 2,
            'senior_situacao_original' => 'LIQ',
            'due_date' => '2026-06-10',
        ]);
        $this->makeReceivable([
            'customer_name' => 'Outro',
            'codemp' => 3,
            'senior_situacao_original' => 'AB',
            'due_date' => '2026-06-10',
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-receber?status=LIQ&codemp=2&due_from=2026-06-01&due_to=2026-06-30')
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('customer_name')->all();

        $this->assertSame(['Match'], $names);
    }
}
