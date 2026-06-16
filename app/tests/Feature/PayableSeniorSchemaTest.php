<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableRateio;
use App\Models\PayableSyncRun;
use Database\Seeders\PayableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Camada de dados da spec senior-contas-pagar-sync (incremento 1):
 * espelhamento dos campos da Senior em payables + rateios + sync runs,
 * e o DemoSeeder/PayableSeeder populando a massa local (requirement 12).
 */
class PayableSeniorSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_payables_tem_todas_as_colunas_senior(): void
    {
        $payable = Payable::create([
            'supplier_name' => 'Fornecedor X',
            'amount' => 100.00,
            'due_date' => '2026-06-30',
            'senior_id' => '1-1-TIT-0001-1000',
        ]);

        // Toda coluna de cabeçalho do Apêndice A.2 deve existir e ser gravável.
        $valores = [];
        foreach (Payable::seniorHeaderFields() as $code => $type) {
            $col = Payable::seniorColumn($code);
            $valores[$col] = match ($type) {
                'money', 'rate' => 12.34,
                'date' => '2026-01-15',
                'int' => 7,
                default => 'ABC',
            };
        }
        $payable->fill($valores)->save();

        $fresh = $payable->fresh();
        $this->assertEquals(7, $fresh->codemp);
        $this->assertEquals('ABC', $fresh->numtit);
        // Cast de data → Carbon.
        $this->assertEquals('2026-01-15', $fresh->datemi->toDateString());
        // Cast de dinheiro → string decimal com 2 casas.
        $this->assertEquals('12.34', (string) $fresh->vlrori);
    }

    public function test_senior_id_e_unico(): void
    {
        Payable::create([
            'supplier_name' => 'A', 'amount' => 1, 'due_date' => '2026-06-30',
            'senior_id' => 'KEY-DUP',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Payable::create([
            'supplier_name' => 'B', 'amount' => 2, 'due_date' => '2026-06-30',
            'senior_id' => 'KEY-DUP',
        ]);
    }

    public function test_rateios_relacao_e_casts(): void
    {
        $payable = Payable::create([
            'supplier_name' => 'Com Rateio', 'amount' => 1000, 'due_date' => '2026-06-30',
            'senior_id' => '1-1-TIT-0002-1001',
        ]);

        $payable->rateios()->create([
            'perrat' => 60, 'percta' => 60, 'vlrrat' => 600, 'vlrcta' => 600, 'seqrat' => 1,
        ]);
        $payable->rateios()->create([
            'perrat' => 40, 'percta' => 40, 'vlrrat' => 400, 'vlrcta' => 400, 'seqrat' => 2,
        ]);

        $this->assertCount(2, $payable->refresh()->rateios);
        $this->assertEquals(100, $payable->rateios->sum(fn (PayableRateio $r) => (float) $r->perrat));
        $this->assertInstanceOf(Payable::class, $payable->rateios->first()->payable);
    }

    public function test_is_missing_in_senior(): void
    {
        $p = Payable::create([
            'supplier_name' => 'Y', 'amount' => 1, 'due_date' => '2026-06-30',
            'senior_id' => '1-1-TIT-0003-1002',
        ]);

        $this->assertFalse($p->isMissingInSenior());

        $p->update(['senior_missing_at' => now()]);
        $this->assertTrue($p->fresh()->isMissingInSenior());
    }

    public function test_sync_run_model(): void
    {
        $run = PayableSyncRun::create([
            'environment' => 'HML',
            'mode' => PayableSyncRun::MODE_INCREMENTAL,
            'trigger' => PayableSyncRun::TRIGGER_MANUAL,
            'status' => PayableSyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $this->assertTrue($run->isRunning());
        $this->assertEquals(0, $run->inserted_count);
    }

    // ─── DemoSeeder / PayableSeeder (requirement 12) ─────────────────────────────

    public function test_seeder_popula_campos_senior_nao_nulos(): void
    {
        $this->seed(PayableSeeder::class);

        $this->assertGreaterThanOrEqual(35, Payable::count());

        $payable = Payable::whereNotNull('senior_id')->first();
        $this->assertNotNull($payable);

        // req 12.1: todos os Senior_Origin_Fields preenchidos (não nulos).
        foreach (Payable::seniorColumns() as $col) {
            $this->assertNotNull($payable->getAttribute($col), "Coluna Senior nula: {$col}");
        }
    }

    public function test_seeder_gera_senior_id_unico(): void
    {
        $this->seed(PayableSeeder::class);

        $total = Payable::whereNotNull('senior_id')->count();
        $distintos = Payable::whereNotNull('senior_id')->distinct('senior_id')->count('senior_id');

        $this->assertEquals($total, $distintos, 'senior_id deve ser único (req 12.3)');
    }

    public function test_seeder_rateios_somam_100(): void
    {
        $this->seed(PayableSeeder::class);

        $comRateio = Payable::has('rateios')->with('rateios')->get();
        $this->assertGreaterThan(0, $comRateio->count());

        foreach ($comRateio as $p) {
            $this->assertLessThanOrEqual(5, $p->rateios->count());
            $this->assertGreaterThanOrEqual(1, $p->rateios->count());
            $soma = $p->rateios->sum(fn (PayableRateio $r) => (float) $r->perrat);
            $this->assertEquals(100.0, round($soma, 2), "Rateios do título {$p->id} não somam 100%");
        }
    }
}
