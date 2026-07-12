<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableAllocationLine;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PayableAllocationImportTest extends TestCase
{
    use RefreshDatabase;

    private function userWithView(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => '*'], ['label' => '*', 'module' => 'system'])->id
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Ver CP', 'module' => 'financeiro'],
            )->id
        );

        return $user;
    }

    private function makePayable(float $amount = 280.00, string $status = 'pago'): Payable
    {
        return Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Six Academia',
            'amount' => $amount,
            'due_date' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    private function fixtureFile(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/fixtures/payable-allocation-sample.xlsx'),
            'pagamento-quadro-fixo.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    public function test_imports_allocation_lines_from_spreadsheet(): void
    {
        $user = $this->userWithView();
        $payable = $this->makePayable();

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/rateio/importar", [
                'file' => $this->fixtureFile(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(2, PayableAllocationLine::where('payable_id', $payable->id)->count());

        $lines = PayableAllocationLine::where('payable_id', $payable->id)->orderBy('line_order')->get();
        $this->assertEquals('ANA CRISTINA DOS SANTOS', trim($lines[0]->person_name));
        $this->assertEquals('297.765.891-20', $lines[0]->document_id);
        $this->assertEquals('ASCENSORISTA', $lines[0]->role_label);
        $this->assertEquals('61 992154154', $lines[0]->pix_key);
        $this->assertEquals(140.00, (float) $lines[0]->amount);

        $payable->refresh();
        $this->assertNotNull($payable->allocation_imported_at);
        $this->assertEquals($user->id, $payable->allocation_imported_by);
        $this->assertEquals('pagamento-quadro-fixo.xlsx', $payable->allocation_source_file);
    }

    public function test_reimport_replaces_previous_lines(): void
    {
        $user = $this->userWithView();
        $payable = $this->makePayable();

        PayableAllocationLine::create([
            'payable_id' => $payable->id,
            'line_order' => 99,
            'person_name' => 'Linha antiga',
            'amount' => 10,
        ]);

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/rateio/importar", [
                'file' => $this->fixtureFile(),
            ])
            ->assertRedirect();

        $this->assertEquals(2, PayableAllocationLine::where('payable_id', $payable->id)->count());
        $this->assertDatabaseMissing('payable_allocation_lines', [
            'payable_id' => $payable->id,
            'person_name' => 'Linha antiga',
        ]);
    }

    public function test_warns_when_total_differs_from_payable_amount(): void
    {
        $user = $this->userWithView();
        $payable = $this->makePayable(500.00);

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/rateio/importar", [
                'file' => $this->fixtureFile(),
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_blocks_import_when_status_encerrado(): void
    {
        $user = $this->userWithView();
        $payable = $this->makePayable(status: 'encerrado');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/rateio/importar", [
                'file' => $this->fixtureFile(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals(0, PayableAllocationLine::count());
    }

    public function test_show_page_includes_allocation_lines(): void
    {
        $user = $this->userWithView();
        $payable = $this->makePayable();

        PayableAllocationLine::create([
            'payable_id' => $payable->id,
            'line_order' => 1,
            'person_name' => 'Teste',
            'amount' => 100,
        ]);

        $this->actingAs($user)
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Show', false)
                ->has('payable.allocation_lines', 1)
                ->where('canImportAllocations', true)
            );
    }
}
