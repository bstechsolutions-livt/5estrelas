<?php

namespace Tests\Feature;

use App\Services\Senior\PayableMapper;
use App\Services\Senior\SeniorCpClient;
use App\Services\Senior\SeniorException;
use App\Services\Senior\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unidade dos componentes de integração Senior (sem rede):
 * Status_Mapper, Payable_Mapper e o Senior_CP_Client (envelope + parse).
 * RefreshDatabase porque StatusMapper::map(null) grava auditoria.
 */
class SeniorMappersAndClientTest extends TestCase
{
    use RefreshDatabase;

    // ─── Status_Mapper (req 8) ──────────────────────────────────────────────────

    public function test_status_mapper_traduz_codigos_conhecidos(): void
    {
        $m = new StatusMapper();
        $this->assertEquals('pendente', $m->map('NOR'));
        $this->assertEquals('pago', $m->map('PAG'));
        $this->assertEquals('reprovado', $m->map('CAN'));
        $this->assertEquals('aprovado', $m->map('apr')); // case-insensitive
    }

    public function test_status_mapper_desconhecido_e_nulo_viram_pendente(): void
    {
        $m = new StatusMapper();
        $this->assertEquals('pendente', $m->map('XYZ')); // req 8.4
        $this->assertEquals('pendente', $m->map(null));  // req 8.5
        $this->assertEquals('pendente', $m->map(''));

        // req 8.5: situação indefinida gera log de auditoria.
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.sync.situacao_indefinida']);
    }

    // ─── Payable_Mapper (req 3, 4.7) ────────────────────────────────────────────

    public function test_business_key_deriva_e_falha_quando_incompleta(): void
    {
        $m = new PayableMapper();
        $this->assertEquals('1-2-TIT-9-DP-500', $m->businessKey([
            'codEmp' => 1, 'codFil' => 2, 'numTit' => 'TIT-9', 'codTpt' => 'DP', 'codFor' => 500,
        ]));

        // Falta codFor → null (req 4.7).
        $this->assertNull($m->businessKey([
            'codEmp' => 1, 'codFil' => 2, 'numTit' => 'TIT-9', 'codTpt' => 'DP',
        ]));
    }

    public function test_map_header_converte_valores_datas_e_nulos(): void
    {
        $m = new PayableMapper();
        $attrs = $m->mapHeader([
            'codEmp' => 1, 'codFil' => 2, 'numTit' => 'TIT-9', 'codTpt' => 'DP', 'codFor' => 500,
            'sitTit' => 'NOR',
            'vlrOri' => '1.234,56',      // formato BR
            'vlrAbe' => '1234.56',       // formato US
            'datEmi' => '15/01/2026',    // data BR
            'vctPro' => '2026-02-20',    // data ISO
            'codMoe' => '', // vazio → null
        ]);

        $this->assertEquals(1234.56, (float) $attrs['vlrori']);
        $this->assertEquals(1234.56, (float) $attrs['vlrabe']);
        $this->assertEquals('2026-01-15', $attrs['datemi']);
        $this->assertEquals('2026-02-20', $attrs['due_date']);
        $this->assertEquals('TIT-9', $attrs['title_number']);
        $this->assertNull($attrs['codmoe']);
        $this->assertEquals('NOR', $attrs['senior_situacao_original']);
        $this->assertIsArray($attrs['senior_raw']);
    }

    public function test_map_header_falha_de_conversao_vira_nulo(): void
    {
        $m = new PayableMapper();
        $attrs = $m->mapHeader([
            'codEmp' => 1, 'codFil' => 2, 'numTit' => 'TIT-9', 'codTpt' => 'DP', 'codFor' => 500,
            'vlrOri' => 'abc',        // dinheiro inválido → null (req 3.8)
            'datEmi' => '99/99/9999', // data inválida → null
        ]);

        $this->assertNull($attrs['vlrori']);
        $this->assertNull($attrs['datemi']);
    }

    // ─── Senior_CP_Client: envelope + parse (req 1, 2.5) ────────────────────────

    private function client(): SeniorCpClient
    {
        return new SeniorCpClient([
            'environment' => 'HML',
            'endpoints' => ['HML' => 'https://hml.example/g5-senior-services'],
            'cp_service' => 'sapiens_Synccom_senior_g5_co_mfi_cpa_titulos',
            'credentials' => ['user' => 'u', 'password' => 'p', 'encryption' => '0'],
            'cod_emp' => 1, 'batch_size' => 500,
        ]);
    }

    public function test_build_envelope_inclui_credenciais_e_limpa_controle(): void
    {
        $xml = $this->client()->buildEnvelope(['codEmp' => 1, 'campo' => "a\x01b", 'vazio' => '']);

        $this->assertStringContainsString('<user>u</user>', $xml);
        $this->assertStringContainsString('<password>p</password>', $xml);
        $this->assertStringContainsString('<encryption>0</encryption>', $xml);
        $this->assertStringContainsString('<codEmp>1</codEmp>', $xml);
        // req 2.5: caractere de controle removido.
        $this->assertStringContainsString('<campo>ab</campo>', $xml);
        $this->assertStringNotContainsString("\x01", $xml);
        // valores vazios não viram tag.
        $this->assertStringNotContainsString('<vazio>', $xml);
    }

    public function test_parse_response_extrai_titulos_e_rateios(): void
    {
        $xml = <<<XML
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ns:ConsultarTitulosAbertosCPResponse xmlns:ns="http://services.senior.com.br">
      <result>
        <tipoRetorno>0</tipoRetorno>
        <titulos>
          <codEmp>1</codEmp><codFil>1</codFil><numTit>TIT-1</numTit><codTpt>DP</codTpt><codFor>1000</codFor>
          <sitTit>NOR</sitTit><vlrOri>1234.56</vlrOri>
          <rateios><perRat>60</perRat><vlrRat>740.74</vlrRat><seqRat>1</seqRat></rateios>
          <rateios><perRat>40</perRat><vlrRat>493.82</vlrRat><seqRat>2</seqRat></rateios>
        </titulos>
        <titulos>
          <codEmp>1</codEmp><codFil>1</codFil><numTit>TIT-2</numTit><codTpt>DP</codTpt><codFor>1001</codFor>
          <sitTit>PAG</sitTit><vlrOri>500.00</vlrOri>
        </titulos>
      </result>
    </ns:ConsultarTitulosAbertosCPResponse>
  </soap:Body>
</soap:Envelope>
XML;

        $res = $this->client()->parseResponse($xml);
        $this->assertCount(2, $res['titulos']);
        $this->assertEquals('TIT-1', $res['titulos'][0]['numTit']);
        $this->assertCount(2, $res['titulos'][0]['rateios']);
        $this->assertEquals('60', (string) $res['titulos'][0]['rateios'][0]['perRat']);
        $this->assertCount(0, $res['titulos'][1]['rateios']);
    }

    public function test_parse_response_detecta_erro_de_negocio(): void
    {
        $xml = <<<XML
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ns:Resp xmlns:ns="http://services.senior.com.br">
      <result>
        <tipoRetorno>1</tipoRetorno>
        <mensagemRetorno>Falha de autenticacao</mensagemRetorno>
      </result>
    </ns:Resp>
  </soap:Body>
</soap:Envelope>
XML;

        $this->expectException(SeniorException::class);
        $this->expectExceptionMessage('Falha de autenticacao');
        $this->client()->parseResponse($xml);
    }
}
