<?php

namespace Tests\Unit\Services\Senior;

use App\Services\Senior\PayableMapper;
use App\Services\Senior\SeniorPrjContasPagarClient;
use Tests\TestCase;

class PayableLauncherUsuGerTest extends TestCase
{
    public function test_mapper_prioriza_usuger_sobre_codusu(): void
    {
        $cod = PayableMapper::resolveLauncherCodUsu([
            'UsuGer' => '166.0',
            'codUsu' => '99',
            'codFav' => '1',
        ]);

        $this->assertSame(166, $cod);
    }

    public function test_mapper_retorna_null_quando_usuger_zero(): void
    {
        $cod = PayableMapper::resolveLauncherCodUsu([
            'UsuGer' => 0,
            'codUsu' => 0,
            'codFav' => 0,
        ]);

        $this->assertNull($cod);
    }

    public function test_parse_titulos_extrai_usuger(): void
    {
        $client = SeniorPrjContasPagarClient::fromConfig();
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
  <S:Body>
    <ns2:ExportarResponse xmlns:ns2="http://services.senior.com.br">
      <result>
        <mensagemRetorno>Processado com sucesso.</mensagemRetorno>
        <tipoRetorno>1</tipoRetorno>
        <ContaPagar>
          <CodEmp>2</CodEmp>
          <CodFil>6</CodFil>
          <CodFor>33992943</CodFor>
          <CodTpt>01</CodTpt>
          <NumTit>462</NumTit>
          <UsuGer>166.0</UsuGer>
        </ContaPagar>
      </result>
    </ns2:ExportarResponse>
  </S:Body>
</S:Envelope>
XML;

        $rows = $client->parseTitulos($xml);

        $this->assertCount(1, $rows);
        $this->assertSame(166, $rows[0]['UsuGer']);
        $this->assertSame('462', $rows[0]['NumTit']);
        $this->assertSame(2, $rows[0]['CodEmp']);
        $this->assertSame(6, $rows[0]['CodFil']);
    }
}
