<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableSyncRun;
use App\Services\Senior\PayableMapper;
use App\Services\Senior\PayablesSyncService;
use App\Services\Senior\SeniorCpClient;
use App\Services\Senior\SeniorException;
use App\Services\Senior\StatusMapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payables_Sync (requirements 2, 4, 5, 6, 7, 8, 9, 12): upsert idempotente,
 * preservação de Workflow_Fields, ausentes, concorrência e modo desabilitado.
 * Usa um Senior_CP_Client fake (sem rede).
 */
class PayablesSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function titulo(string $num, string $sit = 'NOR', float $vlr = 1000, array $rateios = []): array
    {
        return [
            'codEmp' => 1, 'codFil' => 1, 'numTit' => $num, 'codTpt' => 'DP', 'codFor' => 1000,
            'sitTit' => $sit, 'vlrOri' => $vlr, 'vlrAbe' => $vlr, 'vctPro' => '2026-07-01',
            'rateios' => $rateios ?: [['perRat' => 100, 'vlrRat' => $vlr, 'seqRat' => 1]],
        ];
    }

    private function service(array $titulos): PayablesSyncService
    {
        $fake = new class($titulos) extends SeniorCpClient {
            public function __construct(private array $fakeTitulos)
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosAbertos(?Carbon $vctIni, ?Carbon $vctFim): array
            {
                return $this->fakeTitulos;
            }
        };

        return new PayablesSyncService($fake, new PayableMapper(), new StatusMapper());
    }

    private function serviceQueFalha(): PayablesSyncService
    {
        $fake = new class extends SeniorCpClient {
            public function __construct()
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosAbertos(?Carbon $vctIni, ?Carbon $vctFim): array
            {
                throw new SeniorException('indisponível', SeniorException::KIND_UNAVAILABLE);
            }
        };

        return new PayablesSyncService($fake, new PayableMapper(), new StatusMapper());
    }

    // ─── Modo desabilitado (req 12.4 / 12.5) ────────────────────────────────────

    public function test_desabilitado_nao_toca_nos_dados(): void
    {
        config(['senior.enabled' => false]);

        $run = $this->service([$this->titulo('TIT-1')])->run(PayableSyncRun::MODE_FULL);

        $this->assertEquals(PayableSyncRun::STATUS_SKIPPED, $run->status);
        $this->assertEquals(0, Payable::count());
    }

    // ─── Concorrência (req 6.5) ─────────────────────────────────────────────────

    public function test_execucao_concorrente_e_ignorada(): void
    {
        config(['senior.enabled' => true]);

        PayableSyncRun::create([
            'environment' => 'HML', 'mode' => 'incremental', 'trigger' => 'agendado',
            'status' => PayableSyncRun::STATUS_RUNNING, 'started_at' => now(), 'finished_at' => null,
        ]);

        $run = $this->service([$this->titulo('TIT-1')])->run();

        $this->assertEquals(PayableSyncRun::STATUS_SKIPPED, $run->status);
        $this->assertEquals(0, Payable::count());
    }

    // ─── Upsert + idempotência (req 4) ──────────────────────────────────────────

    public function test_insere_e_e_idempotente(): void
    {
        config(['senior.enabled' => true]);
        $titulos = [$this->titulo('TIT-1', 'NOR', 1000), $this->titulo('TIT-2', 'PAG', 500)];
        $titulos[1]['codFor'] = 1001;

        $run1 = $this->service($titulos)->run(PayableSyncRun::MODE_FULL);
        $this->assertEquals(2, $run1->inserted_count);
        $this->assertEquals(2, Payable::count());

        $p1 = Payable::where('title_number', 'TIT-1')->first();
        $this->assertEquals('pendente', $p1->status); // NOR → pendente
        $this->assertEquals(1, $p1->rateios->count());

        // Segunda execução sem mudanças → 0 inseridos, 0 atualizados (req 4.5).
        $run2 = $this->service($titulos)->run(PayableSyncRun::MODE_FULL);
        $this->assertEquals(0, $run2->inserted_count);
        $this->assertEquals(0, $run2->updated_count);
        $this->assertEquals(2, Payable::count());
    }

    public function test_atualiza_quando_conteudo_muda(): void
    {
        config(['senior.enabled' => true]);
        $this->service([$this->titulo('TIT-1', 'NOR', 1000)])->run(PayableSyncRun::MODE_FULL);

        $run = $this->service([$this->titulo('TIT-1', 'NOR', 2500)])->run(PayableSyncRun::MODE_FULL);
        $this->assertEquals(1, $run->updated_count);
        $this->assertEquals('2500.00', (string) Payable::where('title_number', 'TIT-1')->first()->vlrori);
    }

    public function test_preserva_workflow_status_no_update(): void
    {
        config(['senior.enabled' => true]);
        $this->service([$this->titulo('TIT-1', 'NOR', 1000)])->run(PayableSyncRun::MODE_FULL);

        // Workflow interno avança.
        $p = Payable::where('title_number', 'TIT-1')->first();
        $p->update(['status' => 'aprovado']);

        // Senior muda o valor; o status interno NÃO pode ser sobrescrito (req 4.4 / 8.3).
        $this->service([$this->titulo('TIT-1', 'PAG', 3000)])->run(PayableSyncRun::MODE_FULL);

        $p->refresh();
        $this->assertEquals('aprovado', $p->status);
        $this->assertEquals('3000.00', (string) $p->vlrori);
    }

    // ─── Ausentes (req 7) ───────────────────────────────────────────────────────

    public function test_marca_e_limpa_ausente_no_full_sync(): void
    {
        config(['senior.enabled' => true]);
        // Carrega 2 títulos.
        $t1 = $this->titulo('TIT-1');
        $t2 = $this->titulo('TIT-2');
        $t2['codFor'] = 1001;
        $this->service([$t1, $t2])->run(PayableSyncRun::MODE_FULL);

        // Próximo full sync só retorna TIT-1 → TIT-2 fica ausente (req 7.1).
        $run = $this->service([$t1])->run(PayableSyncRun::MODE_FULL);
        $this->assertEquals(1, $run->missing_count);
        $this->assertNotNull(Payable::where('title_number', 'TIT-2')->first()->senior_missing_at);
        // TIT-2 preservado fisicamente (req 7.3).
        $this->assertNotNull(Payable::where('title_number', 'TIT-2')->first());

        // TIT-2 volta → ausência limpa (req 7.4).
        $this->service([$t1, $t2])->run(PayableSyncRun::MODE_FULL);
        $this->assertNull(Payable::where('title_number', 'TIT-2')->first()->senior_missing_at);
    }

    public function test_incremental_nao_marca_ausentes(): void
    {
        config(['senior.enabled' => true]);
        $t1 = $this->titulo('TIT-1');
        $t2 = $this->titulo('TIT-2');
        $t2['codFor'] = 1001;
        $this->service([$t1, $t2])->run(PayableSyncRun::MODE_FULL);

        // Incremental retornando só TIT-1 não pode marcar TIT-2 como ausente (req 7.2).
        $run = $this->service([$t1])->run(PayableSyncRun::MODE_INCREMENTAL);
        $this->assertEquals(0, $run->missing_count);
        $this->assertNull(Payable::where('title_number', 'TIT-2')->first()->senior_missing_at);
    }

    // ─── Falha de comunicação (req 2.3 / 9.4) ───────────────────────────────────

    public function test_falha_de_comunicacao_nao_altera_dados(): void
    {
        config(['senior.enabled' => true]);
        $this->service([$this->titulo('TIT-1')])->run(PayableSyncRun::MODE_FULL);
        $antes = Payable::count();

        $run = $this->serviceQueFalha()->run(PayableSyncRun::MODE_FULL);

        $this->assertEquals(PayableSyncRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('indisponível', $run->error_message);
        $this->assertEquals($antes, Payable::count());
    }

    // ─── Observabilidade (req 9) ────────────────────────────────────────────────

    public function test_registra_contagens_e_auditoria(): void
    {
        config(['senior.enabled' => true]);
        $run = $this->service([$this->titulo('TIT-1')])->run(PayableSyncRun::MODE_FULL);

        $this->assertEquals(PayableSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertEquals(1, $run->inserted_count);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.sync.concluido']);
    }
}
