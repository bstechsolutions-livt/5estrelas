<?php

namespace App\Services\Senior;

use Illuminate\Support\Facades\Http;

/**
 * Cliente SOAP read-only do cadastro de fornecedores (cad_fornecedor / ConsultarGeral).
 */
class SeniorFornecedorClient
{
    private const SOAP_NS = 'http://services.senior.com.br';

    public function __construct(private array $config)
    {
    }

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
        $service = $this->config['fornecedor_service'] ?? 'sapiens_Synccom_senior_g5_co_cad_fornecedor';

        return rtrim($this->endpointBase(), '/') . '/' . $service;
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1);
    }

    public function buildEnvelope(array $params): string
    {
        $cred = $this->config['credentials'];
        $user = $this->esc($this->sanitize((string) ($cred['user'] ?? '')));
        $pass = $this->esc($this->sanitize((string) ($cred['password'] ?? '')));
        $enc = $this->esc($this->sanitize((string) ($cred['encryption'] ?? '0')));

        $paramXml = '';
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $val = htmlspecialchars($this->sanitize((string) $v), ENT_XML1);
            $paramXml .= "<{$k}>{$val}</{$k}>";
        }

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="{$this->soapNs()}">
  <soapenv:Header/>
  <soapenv:Body>
    <ser:ConsultarGeral>
      <user>{$user}</user>
      <password>{$pass}</password>
      <encryption>{$enc}</encryption>
      <parameters>{$paramXml}</parameters>
    </ser:ConsultarGeral>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function soapNs(): string
    {
        return self::SOAP_NS;
    }

    public function parseResponse(string $xml): array
    {
        $clean = $this->stripNamespaces($xml);
        $sx = @simplexml_load_string($clean);
        if ($sx === false) {
            throw new SeniorException('Resposta SOAP inválida (XML não pôde ser lido)', SeniorException::KIND_UNAVAILABLE);
        }

        $arr = json_decode(json_encode($sx), true) ?: [];
        $flat = $this->findResultNode($arr);

        $tipoRetorno = is_array($flat['tipoRetorno'] ?? null) ? null : ($flat['tipoRetorno'] ?? null);
        $erroExecucao = $flat['erroExecucao'] ?? null;
        $temErroExec = $erroExecucao !== null && !is_array($erroExecucao) && trim((string) $erroExecucao) !== '';

        $sucesso = ((string) ($tipoRetorno ?? '') === '1') && !$temErroExec;
        if (!$sucesso) {
            throw new SeniorException($this->errorMessage($flat, $erroExecucao), SeniorException::KIND_BUSINESS);
        }

        return ['fornecedores' => $this->extractFornecedores($flat)];
    }

    private function errorMessage(array $flat, mixed $erroExecucao): string
    {
        if (isset($flat['erros']) && is_array($flat['erros'])) {
            $msgErro = $flat['erros']['mensagemErro'] ?? null;
            if (is_array($msgErro)) {
                $msgErro = implode('; ', array_map('strval', $msgErro));
            }
            if ($msgErro !== null && trim((string) $msgErro) !== '') {
                return trim((string) $msgErro);
            }
        }
        if ($erroExecucao !== null && !is_array($erroExecucao) && trim((string) $erroExecucao) !== '') {
            return trim((string) $erroExecucao);
        }
        $msgRet = $flat['mensagemRetorno'] ?? null;
        if ($msgRet !== null && !is_array($msgRet) && trim((string) $msgRet) !== '') {
            return trim((string) $msgRet);
        }

        return 'Erro retornado pela Senior';
    }

    private function extractFornecedores(array $flat): array
    {
        $raw = $flat['fornecedor'] ?? $flat['fornecedores'] ?? [];
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        if (!isset($raw[0]) && !isset($raw['codFor']) && !isset($raw['codEmp'])) {
            return [];
        }
        if (array_keys($raw) !== range(0, count($raw) - 1)) {
            $raw = [$raw];
        }

        return array_values(array_filter(array_map(
            fn ($f) => is_array($f) ? $f : null,
            $raw,
        )));
    }

    private function stripNamespaces(string $xml): string
    {
        $xml = preg_replace('/\sxmlns(:[A-Za-z0-9_]+)?="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace('/(<\/?)[A-Za-z0-9_]+:/', '$1', $xml) ?? $xml;

        return preg_replace('/\s[A-Za-z0-9_]+:([A-Za-z0-9_]+=)/', ' $1', $xml) ?? $xml;
    }

    private function findResultNode(array $arr): array
    {
        $markers = ['fornecedor', 'fornecedores', 'tipoRetorno', 'mensagemRetorno', 'erroExecucao', 'erros'];
        if (array_intersect($markers, array_keys($arr))) {
            return $arr;
        }
        foreach ($arr as $v) {
            if (is_array($v)) {
                $found = $this->findResultNode($v);
                if ($found !== []) {
                    return $found;
                }
            }
        }

        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function consultarGeral(int $codEmp, int $codFil = 1, int $indicePagina = 1, int $limitePagina = 100): array
    {
        $params = [
            'codEmp' => $codEmp,
            'codFil' => $codFil,
            'identificadorSistema' => $this->config['identificador_sistema'] ?? 'EASYTECH',
            'indicePagina' => $indicePagina,
            'limitePagina' => $limitePagina,
        ];

        return $this->callOnce($params);
    }

    private function callOnce(array $params): array
    {
        $envelope = $this->buildEnvelope($params);
        $connect = $this->clamp((int) ($this->config['timeout_connect'] ?? 60), 5, 300);
        $response = $this->clamp((int) ($this->config['timeout_response'] ?? 60), 5, 300);
        $maxRetries = (int) ($this->config['max_retries'] ?? 3);

        $attempt = 0;
        $backoff = [2, 4, 8];
        while (true) {
            try {
                $res = Http::withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '',
                ])
                    ->connectTimeout($connect)
                    ->timeout($response)
                    ->withBody($envelope, 'text/xml')
                    ->post($this->serviceUrl());

                if ($res->failed()) {
                    throw new SeniorException("Senior respondeu HTTP {$res->status()}", SeniorException::KIND_UNAVAILABLE);
                }

                return $this->parseResponse($res->body())['fornecedores'];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $kind = str_contains(strtolower($e->getMessage()), 'timed out')
                    ? SeniorException::KIND_TIMEOUT
                    : SeniorException::KIND_UNAVAILABLE;
                $ex = new SeniorException($e->getMessage(), $kind);
                if ($attempt >= $maxRetries) {
                    throw $ex;
                }
            } catch (SeniorException $e) {
                if (!$e->isTransient() || $attempt >= $maxRetries) {
                    throw $e;
                }
            }

            sleep($backoff[$attempt] ?? 8);
            $attempt++;
        }
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
