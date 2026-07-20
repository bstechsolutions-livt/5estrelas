<?php

namespace Tests\Unit\Services\Senior;

use App\Services\Senior\FornecedorMapper;
use App\Services\Senior\SeniorFornecedorClient;
use Tests\TestCase;

class SeniorFornecedorClientTest extends TestCase
{
    public function test_offset_for_page_usa_deslocamento_senior(): void
    {
        $this->assertSame(1, SeniorFornecedorClient::offsetForPage(1, 100));
        $this->assertSame(101, SeniorFornecedorClient::offsetForPage(2, 100));
        $this->assertSame(201, SeniorFornecedorClient::offsetForPage(3, 100));
    }

    public function test_exportar_envelope_usa_tipo_e_e_codfor(): void
    {
        $client = new SeniorFornecedorClient([
            'credentials' => ['user' => 'u', 'password' => 'p', 'encryption' => '0'],
            'identificador_sistema' => 'EASYTECH',
        ]);

        $xml = $client->buildEnvelope([
            'codEmp' => 2,
            'codFil' => 1,
            'identificadorSistema' => 'EASYTECH',
            'codFor' => '3944',
            'tipoIntegracao' => 'E',
            'quantidadeRegistros' => 1,
        ], 'Exportar');

        $this->assertStringContainsString('<ser:Exportar>', $xml);
        $this->assertStringContainsString('<codFor>3944</codFor>', $xml);
        $this->assertStringContainsString('<tipoIntegracao>E</tipoIntegracao>', $xml);
        $this->assertStringNotContainsString('<ser:ConsultarGeral>', $xml);
    }

    public function test_parse_exportar_sucesso_extrai_fornecedor(): void
    {
        $client = new SeniorFornecedorClient([
            'credentials' => ['user' => 'u', 'password' => 'p', 'encryption' => '0'],
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
  <S:Body>
    <ns2:ExportarResponse xmlns:ns2="http://services.senior.com.br">
      <result>
        <erroExecucao xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="true"/>
        <fornecedor>
          <codEmp>2</codEmp>
          <codFor>3944</codFor>
          <nomFor>VT SOLUCOES ADMINISTRACAO E SERVICOS LTDA</nomFor>
          <apeFor>VT SOLUCOES ADMINISTRACAO E SERVICOS LTDA</apeFor>
          <cgcCpf>19593175000188.0</cgcCpf>
        </fornecedor>
        <mensagemRetorno>Processado com sucesso.</mensagemRetorno>
        <tipoRetorno>0</tipoRetorno>
      </result>
    </ns2:ExportarResponse>
  </S:Body>
</S:Envelope>
XML;

        $rows = $client->parseResponse($xml)['fornecedores'];
        $this->assertCount(1, $rows);
        $this->assertSame('3944', (string) $rows[0]['codFor']);
        $this->assertStringContainsString('VT SOLUCOES', (string) $rows[0]['nomFor']);

        $mapped = (new FornecedorMapper())->map($rows[0]);
        $this->assertSame('19593175000188', $mapped['cnpj']);
        $this->assertSame('VT SOLUCOES ADMINISTRACAO E SERVICOS LTDA', $mapped['name']);
    }
}
