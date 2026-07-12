<?php

namespace Tests\Feature;

use App\Models\Receivable;
use App\Models\ReceivableSyncRun;
use App\Services\Senior\ReceivableMapper;
use App\Services\Senior\ReceivablesSyncService;
use App\Services\Senior\SeniorCrClient;
use App\Services\Senior\SeniorException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivablesSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function titulo(string $num, string $sit = 'AB', float $vlr = 1000, array $rateios = []): array
    {
        return [
            'codEmp' => 3, 'codFil' => 1, 'numTit' => $num, 'codTpt' => 'NFS', 'codCli' => 10,
            'sitTit' => $sit, 'vlrOri' => $vlr, 'vlrAbe' => $vlr, 'vctPro' => '2026-07-12',
            'obsTcr' => 'NF 2705',
            'rateios' => $rateios ?: [['perRat' => 100, 'vlrRat' => $vlr, 'seqRat' => 1, 'codCcu' => '5424', 'ctaFin' => 102040]],
        ];
    }

    private function service(array $titulos): ReceivablesSyncService
    {
        config([
            'senior.cod_emps' => [3],
            'senior.cod_cli_start' => 10,
            'senior.cod_cli_end' => 10,
        ]);

        $fake = new class($titulos) extends SeniorCrClient {
            public function __construct(private array $fakeTitulos)
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosPorCliente(int $codEmp, int $codCli, ?Carbon $vctIni, ?Carbon $vctFim): array
            {
                return array_values(array_filter(
                    $this->fakeTitulos,
                    fn ($t) => (int) ($t['codEmp'] ?? 0) === $codEmp && (int) ($t['codCli'] ?? 0) === $codCli,
                ));
            }
        };

        return new ReceivablesSyncService($fake, new ReceivableMapper());
    }

    public function test_desabilitado_nao_toca_nos_dados(): void
    {
        config(['senior.enabled' => false]);

        $run = $this->service([$this->titulo('2705_01')])->run(ReceivableSyncRun::MODE_FULL);

        $this->assertEquals(ReceivableSyncRun::STATUS_SKIPPED, $run->status);
        $this->assertEquals(0, Receivable::count());
    }

    public function test_insere_titulos_da_senior(): void
    {
        config(['senior.enabled' => true]);

        $run = $this->service([$this->titulo('2705_01')])->run(ReceivableSyncRun::MODE_FULL);

        $this->assertEquals(ReceivableSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertEquals(1, Receivable::count());
        $this->assertDatabaseHas('receivables', [
            'title_number' => '2705_01',
            'senior_situacao_original' => 'AB',
            'codcli' => 10,
        ]);
        $this->assertEquals(1, Receivable::first()->rateios()->count());
    }

    public function test_fixture_xml_real_parse_e_sync(): void
    {
        config(['senior.enabled' => true]);
        $xml = file_get_contents(base_path('tests/fixtures/senior/titulos-abertos-cr-emp3-cli10.xml'));
        $titulos = (new SeniorCrClient(config('senior')))->parseResponse($xml)['titulos'];

        $this->assertNotEmpty($titulos);
        config([
            'senior.cod_emps' => [3],
            'senior.cod_cli_start' => 10,
            'senior.cod_cli_end' => 10,
        ]);

        $fake = new class($titulos) extends SeniorCrClient {
            public function __construct(private array $fakeTitulos)
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosPorCliente(int $codEmp, int $codCli, ?Carbon $vctIni, ?Carbon $vctFim): array
            {
                return $this->fakeTitulos;
            }
        };

        $run = (new ReceivablesSyncService($fake, new ReceivableMapper()))->run(ReceivableSyncRun::MODE_FULL);

        $this->assertEquals(ReceivableSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertEquals(1, $run->inserted_count);
        $this->assertEquals('3-1-2705_01-NFS-10', Receivable::first()->senior_id);
    }

    public function test_falha_de_transporte_marca_run_como_falha(): void
    {
        config(['senior.enabled' => true, 'senior.cod_emps' => [3], 'senior.cod_cli_start' => 10, 'senior.cod_cli_end' => 11, 'senior.sweep_max_transport_failures' => 1]);

        $fake = new class extends SeniorCrClient {
            public function __construct()
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosPorCliente(int $codEmp, int $codCli, ?Carbon $vctIni, ?Carbon $vctFim): array
            {
                throw new SeniorException('indisponível', SeniorException::KIND_UNAVAILABLE);
            }
        };

        $run = (new ReceivablesSyncService($fake, new ReceivableMapper()))->run(ReceivableSyncRun::MODE_FULL);

        $this->assertEquals(ReceivableSyncRun::STATUS_FAILED, $run->status);
    }
}
