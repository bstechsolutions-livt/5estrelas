<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableIssueDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => '*'],
                ['label' => 'Admin', 'module' => 'sistema'],
            )->id,
        );

        return $user;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-'.uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    private function indexJson(User $user, string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar'.$query);
    }

    public function test_index_filtra_por_data_de_emissao(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $this->makePayable([
            'supplier_name' => 'Emitido Hoje',
            'issue_date' => $today,
            'status' => 'pendente',
        ]);
        $this->makePayable([
            'supplier_name' => 'Emitido Ontem',
            'issue_date' => $yesterday,
            'status' => 'pendente',
        ]);
        $this->makePayable([
            'supplier_name' => 'Sem Emissao',
            'issue_date' => null,
            'status' => 'pendente',
        ]);

        $resp = $this->indexJson($this->activeUser(), "?status=pendente&issue_from={$today}&issue_to={$today}")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertSame(['Emitido Hoje'], $names);
    }

    public function test_index_expone_filtros_de_emissao_nas_props(): void
    {
        $this->actingAs($this->activeUser())
            ->get('/financeiro/contas-pagar?status=pendente&issue_from=2026-07-16&issue_to=2026-07-16')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.issue_from', '2026-07-16')
                ->where('filters.issue_to', '2026-07-16')
            );
    }
}
