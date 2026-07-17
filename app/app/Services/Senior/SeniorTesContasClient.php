<?php

namespace App\Services\Senior;

use Illuminate\Support\Facades\Http;

/**
 * Cliente SOAP read-only de tesouraria — contas internas (obterSaldo).
 * Serviço: com.senior.g5.co.mfi.tes.contas
 */
class SeniorTesContasClient
{
    private const SOAP_NS = 'http://services.senior.com.br';

    public function __construct(private array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('senior'));
    }

    private function endpointBase(): string
    {
        $env = strtoupper($this->config['environment'] ?? 'HML');

        return $this->config['endpoints'][$env] ?? $this->config['endpoints']['HML'];
    }

    private function serviceUrl(): string
    {
        $service = $this->config['tes_contas_service']
            ?? 'sapiens_Synccom_senior_g5_co_mfi_tes_contas';

        return rtrim($this->endpointBase(), '/').'/'.$service;
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1);
    }

    public function buildEnvelope(int $indicePagina = 1, int $limitePagina = 1000): string
    {
        $cred = $this->config['credentials'];
        $user = $this->esc($this->sanitize((string) ($cred['user'] ?? '')));
        $pass = $this->esc($this->sanitize((string) ($cred['password'] ?? '')));
        $enc = $this->esc($this->sanitize((string) ($cred['encryption'] ?? '0')));

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="{$this->soapNs()}">
  <soapenv:Header/>
  <soapenv:Body>
    <ser:obterSaldo>
      <user>{$user}</user>
      <password>{$pass}</password>
      <encryption>{$enc}</encryption>
      <parameters>
        <indicePagina>{$indicePagina}</indicePagina>
        <limitePagina>{$limitePagina}</limitePagina>
      </parameters>
    </ser:obterSaldo>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function soapNs(): string
    {
        return self::SOAP_NS;
    }

    /**
     * @return list<array{
     *   codigoEmpresa: int,
     *   nomeEmpresa: ?string,
     *   codigoFilial: int,
     *   nomeFilial: ?string,
     *   numeroConta: string,
     *   descricaoConta: string,
     *   saldo: ?float,
     *   siglaMoeda: ?string
     * }>
     */
    public function obterSaldo(int $indicePagina = 1, int $limitePagina = 1000): array
    {
        $envelope = $this->buildEnvelope($indicePagina, $limitePagina);
        $connect = $this->clamp((int) ($this->config['timeout_connect'] ?? 60), 5, 300);
        $response = $this->clamp((int) ($this->config['timeout_response'] ?? 60), 5, 300);
        $maxRetries = (int) ($this->config['max_retries'] ?? 3);

        $attempt = 0;
        $backoff = [2, 4, 8];
        while (true) {
            try {
                $res = Http::withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => 'obterSaldo',
                ])
                    ->connectTimeout($connect)
                    ->timeout($response)
                    ->withBody($envelope, 'text/xml')
                    ->post($this->serviceUrl());

                if ($res->failed()) {
                    throw new SeniorException("Senior respondeu HTTP {$res->status()}", SeniorException::KIND_UNAVAILABLE);
                }

                return $this->parseResponse($res->body());
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $kind = str_contains(strtolower($e->getMessage()), 'timed out')
                    ? SeniorException::KIND_TIMEOUT
                    : SeniorException::KIND_UNAVAILABLE;
                $ex = new SeniorException($e->getMessage(), $kind);
                if ($attempt >= $maxRetries) {
                    throw $ex;
                }
            } catch (SeniorException $e) {
                if (! $e->isTransient() || $attempt >= $maxRetries) {
                    throw $e;
                }
            }

            sleep($backoff[$attempt] ?? 8);
            $attempt++;
        }
    }

    /**
     * Pagina todas as contas internas disponíveis.
     *
     * @return list<array<string, mixed>>
     */
    public function obterTodasContas(int $pageSize = 1000, int $maxPages = 50): array
    {
        $all = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $chunk = $this->obterSaldo($page, $pageSize);
            if ($chunk === []) {
                break;
            }
            array_push($all, ...$chunk);
            if (count($chunk) < $pageSize) {
                break;
            }
        }

        return $all;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseResponse(string $body): array
    {
        $clean = preg_replace('/<(\/?)\w+:/', '<$1', $body) ?? $body;
        $xml = @simplexml_load_string($clean);
        if (! $xml) {
            throw new SeniorException('Resposta SOAP inválida (obterSaldo).', SeniorException::KIND_BUSINESS);
        }

        $erro = trim((string) ($xml->xpath('//result/erroExecucao')[0] ?? ''));
        if ($erro !== '') {
            throw new SeniorException($erro, SeniorException::KIND_BUSINESS);
        }

        $codigo = trim((string) ($xml->xpath('//result/codigoResultado')[0] ?? ''));
        if ($codigo === '2') {
            $msg = trim((string) ($xml->xpath('//result/resultado')[0] ?? 'Erro na Senior'));
            throw new SeniorException($msg, SeniorException::KIND_BUSINESS);
        }

        $nodes = $xml->xpath('//result/extrato') ?: [];
        $rows = [];
        foreach ($nodes as $n) {
            $num = trim((string) ($n->numeroConta ?? ''));
            if ($num === '') {
                continue;
            }
            $rows[] = [
                'codigoEmpresa' => (int) ($n->codigoEmpresa ?? 0),
                'nomeEmpresa' => trim((string) ($n->nomeEmpresa ?? '')) ?: null,
                'codigoFilial' => (int) ($n->codigoFilial ?? 0),
                'nomeFilial' => trim((string) ($n->nomeFilial ?? '')) ?: null,
                'numeroConta' => $num,
                'descricaoConta' => trim((string) ($n->descricaoConta ?? '')) ?: $num,
                'saldo' => isset($n->saldo) && (string) $n->saldo !== '' ? (float) $n->saldo : null,
                'siglaMoeda' => trim((string) ($n->siglaMoeda ?? '')) ?: null,
            ];
        }

        return $rows;
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
