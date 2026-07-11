<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableOrigemHubBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );

        return $user;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    public function test_senior_nao_recebe_badge(): void
    {
        $this->makePayable([
            'supplier_name' => 'TituloSenior',
            'senior_id' => '3-1-12345-01-1',
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'TituloSenior');

        $this->assertArrayNotHasKey('origem_hub', $row);
    }

    public function test_manual_exibe_badge_hub_na_lista(): void
    {
        $this->makePayable(['supplier_name' => 'TituloHub']);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'TituloHub');

        $this->assertTrue($row['origem_hub']);
    }

    public function test_show_exibe_badge_hub(): void
    {
        $payable = $this->makePayable();

        $this->actingAs($this->activeUser())
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payable.origem_hub', true));
    }
}
