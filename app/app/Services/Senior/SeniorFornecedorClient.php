<?php

namespace App\Services\Senior;

use Illuminate\Support\Facades\Http;

/**
 * Cliente SOAP read-only do cadastro de fornecedores (cad_fornecedor).
 *
 * - ConsultarGeral: catálogo paginado (indicePagina = deslocamento 1-based).
 * - Exportar (tipoIntegracao=E + codFor): lookup pontual pelo código do título.
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

    public function buildEnvelope(array $params, string $operation = 'ConsultarGeral'): string
    {
        $cred = $this->config['credentials'];
        $user = $this->esc($this->sanitize((string) ($cred['user'] ?? '')));
        $pass = $this->esc($this->sanitize((string) ($cred['password'] ?? '')));
        $enc = $this->esc($this->sanitize((string) ($cred['encryption'] ?? '0')));
        $op = preg_replace('/[^A-Za-z0-9_]/', '', $operation) ?: 'ConsultarGeral';

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
    <ser:{$op}>
      <user>{$user}</user>
      <password>{$pass}</password>
      <encryption>{$enc}</encryption>
      <parameters>{$paramXml}</parameters>
    </ser:{$op}>
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

        $tipoRetorno = $this->scalarOrNull($flat['tipoRetorno'] ?? null);
        $erroExecucao = $flat['erroExecucao'] ?? null;
        $temErroExec = $erroExecucao !== null && !is_array($erroExecucao) && trim((string) $erroExecucao) !== '';
        $msgRetorno = $this->scalarOrNull($flat['mensagemRetorno'] ?? null);
        $msgSucesso = is_string($msgRetorno) && stripos($msgRetorno, 'sucesso') !== false;
        $temErroNegocio = $this->hasBusinessError($flat);

        // cad_fornecedor retorna tipoRetorno=0 com "Processado com sucesso." em consultas OK.
        $sucesso = !$temErroExec && !$temErroNegocio && (
            (string) ($tipoRetorno ?? '') === '1'
            || ((string) ($tipoRetorno ?? '') === '0' && $msgSucesso)
        );
        if (!$sucesso) {
            throw new SeniorException($this->errorMessage($flat, $erroExecucao), SeniorException::KIND_BUSINESS);
        }

        return ['fornecedores' => $this->extractFornecedores($flat)];
    }

    private function scalarOrNull(mixed $v): mixed
    {
        return is_array($v) ? null : $v;
    }

    private function hasBusinessError(array $flat): bool
    {
        if (!isset($flat['erros']) || !is_array($flat['erros'])) {
            return false;
        }
        $msgErro = $flat['erros']['mensagemErro'] ?? null;
        if (is_array($msgErro)) {
            $msgErro = implode('; ', array_map('strval', $msgErro));
        }

        return $msgErro !== null && trim((string) $msgErro) !== '';
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
            // Exportar às vezes devolve campos no próprio result.
            if (isset($flat['codFor']) || isset($flat['nomFor'])) {
                return [$flat];
            }

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

    /**
     * ConsultarGeral de fornecedores.
     *
     * Atenção Senior: `indicePagina` é **deslocamento 1-based do primeiro registro**,
     * não número de página. Ex.: para a 2ª fatia de 100 registros, use indice=101.
     *
     * @return array<int, array<string, mixed>>
     */
    public function consultarGeral(int $codEmp, int $codFil = 1, int $indicePagina = 1, int $limitePagina = 100): array
    {
        $params = [
            'codEmp' => $codEmp,
            'codFil' => $codFil,
            'identificadorSistema' => $this->config['identificador_sistema'] ?? 'EASYTECH',
            'indicePagina' => max(1, $indicePagina),
            'limitePagina' => max(1, $limitePagina),
        ];

        return $this->callOnce($params, 'ConsultarGeral');
    }

    /**
     * Converte página 1-based em deslocamento Senior (`indicePagina`).
     * Página 1 → 1, página 2 com limite 100 → 101, etc.
     */
    public static function offsetForPage(int $page, int $pageSize): int
    {
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);

        return (($page - 1) * $pageSize) + 1;
    }

    /**
     * Lookup pontual via Exportar (tipoIntegracao=E + codFor).
     * Contrato validado em PRD 14/07/2026 — ~2s por fornecedor.
     */
    public function exportarPorCodFor(int $codEmp, int $codFor, int $codFil = 1): ?array
    {
        if ($codFor < 1) {
            return null;
        }

        $params = [
            'codEmp' => $codEmp,
            'codFil' => $codFil,
            'identificadorSistema' => $this->config['identificador_sistema'] ?? 'EASYTECH',
            'codFor' => (string) $codFor,
            'tipoIntegracao' => 'E',
            'quantidadeRegistros' => 1,
        ];

        try {
            $rows = $this->callOnce($params, 'Exportar');
        } catch (SeniorException $e) {
            if ($e->kind === SeniorException::KIND_BUSINESS) {
                return null;
            }
            throw $e;
        }

        foreach ($rows as $row) {
            if ((int) ($row['codFor'] ?? 0) === $codFor) {
                $row['codEmp'] ??= $codEmp;

                return $row;
            }
        }

        return $rows[0] ?? null;
    }

    /**
     * Busca um fornecedor pelo codFor do título (Exportar pontual).
     */
    public function consultarPorCodFor(int $codEmp, int $codFor, int $codFil = 1): ?array
    {
        return $this->exportarPorCodFor($codEmp, $codFor, $codFil);
    }

    private function callOnce(array $params, string $operation = 'ConsultarGeral'): array
    {
        $envelope = $this->buildEnvelope($params, $operation);
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
