<?php

namespace Tests\Feature;

use App\Models\Comercial\Filial;
use App\Models\Permission;
use App\Models\User;
use App\Services\Senior\FilialMapper;
use App\Services\Senior\FiliaisSyncService;
use App\Services\Senior\SeniorException;
use App\Services\Senior\SeniorFilialClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filiais/empresas do Comercial ESPELHADAS da Senior.
 * Cobre o FiliaisSyncService (modo desabilitado, upsert via client fake,
 * idempotência, preservação de campos locais, erro de negócio que não destrói
 * dados), o FilialMapper e os endpoints (sincronizar/toggle/update + permissão).
 */
class ComercialFilialTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->attachPermissao($this->user, 'comercial.visualizar');
        $this->attachPermissao($this->user, 'comercial.configurar');
    }

    private function attachPermissao(User $user, string $key): void
    {
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'comercial'])->id
        );
    }

    private function userSomenteVisualizar(): User
    {
        $u = User::factory()->create();
        $this->attachPermissao($u, 'comercial.visualizar');
        return $u;
    }

    /** Client fake que devolve filiais fixas (sem rede). */
    private function fakeClient(array $filiais): SeniorFilialClient
    {
        return new class($filiais) extends SeniorFilialClient {
            public function __construct(private array $fakeFiliais)
            {
                parent::__construct([]);
            }

            public function consultarGeral(int $codEmp, int $indicePagina = 1, int $limitePagina = 100): array
            {
                return $this->fakeFiliais;
            }
        };
    }

    /** Client fake que sempre lança erro de negócio (ex.: WS não parametrizado). */
    private function fakeClientErroNegocio(): SeniorFilialClient
    {
        return new class extends SeniorFilialClient {
            public function __construct()
            {
                parent::__construct([]);
            }

            public function consultarGeral(int $codEmp, int $indicePagina = 1, int $limitePagina = 100): array
            {
                throw new SeniorException('Web service não está parametrizado para ser utilizado.', SeniorException::KIND_BUSINESS);
            }
        };
    }

    // ─── Modo desabilitado ───────────────────────────────────
    public function test_sync_desabilitado_ignora_sem_tocar_dados(): void
    {
        config(['senior.enabled' => false]);
        Filial::create(['senior_id' => '2-1', 'cod_emp' => 2, 'nome' => 'Existente', 'tipo' => 'seguranca', 'ativo' => true]);

        $r = FiliaisSyncService::make()->run();

        $this->assertSame('skipped', $r['status']);
        $this->assertSame(1, Filial::count()); // intacto
    }

    // ─── Upsert via client fake ──────────────────────────────
    public function test_sync_insere_e_e_idempotente(): void
    {
        config(['senior.enabled' => true, 'senior.cod_emps' => [2]]);
        $svc = new FiliaisSyncService($this->fakeClient([
            ['codEmp' => 2, 'codFil' => 1, 'nenFil' => '5 ESTRELAS SEGURANCA LTDA', 'nomFil' => 'MATRIZ', 'numCgc' => '12345678000199', 'sigUfs' => 'DF'],
        ]), new FilialMapper());

        $r = $svc->run();
        $this->assertSame('success', $r['status']);
        $this->assertSame(1, $r['inserted']);
        $this->assertDatabaseHas('bs_comercial_filiais', [
            'senior_id' => '2-1', 'cod_emp' => 2, 'nome' => '5 ESTRELAS SEGURANCA LTDA', 'cnpj' => '12345678000199', 'uf' => 'DF',
        ]);

        // Roda de novo com o mesmo payload → idempotente (nada muda).
        $r2 = $svc->run();
        $this->assertSame(0, $r2['inserted']);
        $this->assertSame(0, $r2['updated']);
    }

    public function test_sync_preserva_tipo_e_tag_local(): void
    {
        config(['senior.enabled' => true, 'senior.cod_emps' => [2]]);
        $f = Filial::create([
            'senior_id' => '2-1', 'cod_emp' => 2, 'cod_fil' => 1, 'nome' => 'Antigo',
            'tipo' => 'seguranca', 'tag' => '5E', 'ativo' => true, 'senior_raw' => ['velho' => true],
        ]);

        (new FiliaisSyncService($this->fakeClient([
            ['codEmp' => 2, 'codFil' => 1, 'nenFil' => 'Nome Atualizado', 'numCgc' => '999', 'sigUfs' => 'GO'],
        ]), new FilialMapper()))->run();

        $f->refresh();
        $this->assertSame('Nome Atualizado', $f->nome); // origem Senior atualizada
        $this->assertSame('GO', $f->uf);
        $this->assertSame('seguranca', $f->tipo);        // apresentação local preservada
        $this->assertSame('5E', $f->tag);                // apresentação local preservada
    }

    public function test_sync_erro_de_negocio_nao_destroi_dados(): void
    {
        config(['senior.enabled' => true, 'senior.cod_emps' => [2]]);
        Filial::create(['senior_id' => '2-1', 'cod_emp' => 2, 'nome' => 'Mantida', 'tipo' => 'seguranca', 'ativo' => true]);

        $r = (new FiliaisSyncService($this->fakeClientErroNegocio(), new FilialMapper()))->run();

        $this->assertSame('success', $r['status']); // erro de negócio não derruba o sync
        $this->assertSame(1, $r['errors']);
        $this->assertSame(1, Filial::count());        // nada destruído
    }

    // ─── Mapper ──────────────────────────────────────────────
    public function test_mapper_business_key_e_header(): void
    {
        $m = new FilialMapper();
        $this->assertSame('2-1', $m->businessKey(['codEmp' => 2, 'codFil' => 1]));
        $this->assertSame('3-1', $m->businessKey(['codEmp' => 3])); // codFil default 1
        $this->assertNull($m->businessKey(['codFil' => 1]));         // sem codEmp

        $h = $m->mapHeader(['codEmp' => 3, 'codFil' => 1, 'nenFil' => 'Razao Social', 'nomFil' => 'Fantasia', 'numCgc' => '123', 'sigUfs' => 'SP']);
        $this->assertSame(3, $h['cod_emp']);
        $this->assertSame('Razao Social', $h['nome']);
        $this->assertSame('Fantasia', $h['fantasia']);
        $this->assertSame('123', $h['cnpj']);
        $this->assertSame('SP', $h['uf']);
    }

    public function test_client_parse_erro_negocio_lanca_excecao(): void
    {
        $client = new SeniorFilialClient(['credentials' => ['user' => 'u', 'password' => 'p', 'encryption' => '0']]);
        $xml = '<x><tipoRetorno>0</tipoRetorno><erroExecucao>Web service não está parametrizado para ser utilizado.</erroExecucao></x>';

        $this->expectException(SeniorException::class);
        $client->parseResponse($xml);
    }

    public function test_client_buildenvelope_inclui_credenciais_e_operacao(): void
    {
        $client = new SeniorFilialClient(['credentials' => ['user' => 'integ', 'password' => 'secret', 'encryption' => '0'], 'identificador_sistema' => 'EASYTECH']);
        $xml = $client->buildEnvelope(['codEmp' => 2, 'identificadorSistema' => 'EASYTECH']);

        $this->assertStringContainsString('<ser:ConsultarGeral>', $xml);
        $this->assertStringContainsString('<user>integ</user>', $xml);
        $this->assertStringContainsString('<password>secret</password>', $xml);
        $this->assertStringContainsString('<codEmp>2</codEmp>', $xml);
        $this->assertStringContainsString('<identificadorSistema>EASYTECH</identificadorSistema>', $xml);
    }

    // ─── Endpoints ───────────────────────────────────────────
    public function test_endpoint_sincronizar_desabilitado_retorna_skipped(): void
    {
        config(['senior.enabled' => false]);

        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/filiais/sincronizar')
            ->assertOk()
            ->assertJson(['status' => 'skipped', 'sucesso' => true]);
    }

    public function test_endpoint_toggle_inverte_ativo(): void
    {
        $f = Filial::create(['senior_id' => '2-1', 'cod_emp' => 2, 'nome' => 'X', 'tipo' => 'seguranca', 'ativo' => true]);

        $this->actingAs($this->user)
            ->patchJson("/comercial/configuracoes/filiais/{$f->id}/toggle")
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_filiais', ['id' => $f->id, 'ativo' => false]);
    }

    public function test_endpoint_update_so_apresentacao_local(): void
    {
        $f = Filial::create(['senior_id' => '2-1', 'cod_emp' => 2, 'nome' => 'Senior Nome', 'tipo' => 'seguranca', 'tag' => 'A', 'ativo' => true]);

        $this->actingAs($this->user)
            ->putJson("/comercial/configuracoes/filiais/{$f->id}", ['tipo' => 'apoio', 'tag' => 'B'])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_filiais', [
            'id' => $f->id, 'tipo' => 'apoio', 'tag' => 'B', 'nome' => 'Senior Nome', // nome (Senior) intacto
        ]);
    }

    public function test_update_rejeita_tipo_invalido(): void
    {
        $f = Filial::create(['senior_id' => '2-1', 'cod_emp' => 2, 'nome' => 'X', 'tipo' => 'seguranca', 'ativo' => true]);

        $this->actingAs($this->user)
            ->putJson("/comercial/configuracoes/filiais/{$f->id}", ['tipo' => 'banana'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_escrita_filial_exige_permissao_configurar(): void
    {
        $semConfig = $this->userSomenteVisualizar();
        $f = Filial::create(['senior_id' => '2-1', 'cod_emp' => 2, 'nome' => 'Protegida', 'tipo' => 'seguranca', 'ativo' => true]);

        $this->actingAs($semConfig)->postJson('/comercial/configuracoes/filiais/sincronizar')->assertStatus(403);
        $this->actingAs($semConfig)->patchJson("/comercial/configuracoes/filiais/{$f->id}/toggle")->assertStatus(403);
        $this->actingAs($semConfig)->putJson("/comercial/configuracoes/filiais/{$f->id}", ['tipo' => 'apoio'])->assertStatus(403);

        $this->assertDatabaseHas('bs_comercial_filiais', ['id' => $f->id, 'tipo' => 'seguranca', 'ativo' => true]);
    }

    public function test_dados_retorna_filiais_com_label_sem_raw(): void
    {
        Filial::create(['senior_id' => '2-1', 'cod_emp' => 2, 'nome' => 'Razao', 'fantasia' => 'Fant', 'tipo' => 'seguranca', 'ativo' => true, 'senior_raw' => ['x' => 1]]);

        $resp = $this->actingAs($this->user)->getJson('/comercial/configuracoes/dados');
        $resp->assertOk()->assertJsonFragment(['label' => 'Fant']);
        // senior_raw não deve vazar
        $this->assertArrayNotHasKey('senior_raw', $resp->json('filiais.0'));
    }
}
