<?php

namespace App\Services\Senior;

use Illuminate\Support\Facades\Http;

/**
 * Cliente SOAP do serviço prj.contaspagar (Exportar / ConsultarGeral).
 * Fonte do lançador do título: campo UsuGer.
 */
class SeniorPrjContasPagarClient
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
        $service = $this->config['prj_contaspagar_service']
            ?? 'sapiens_Synccom_senior_g5_co_prj_contaspagar';

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

    public function buildEnvelope(string $operation, array $params): string
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
    <ser:{$operation}>
      <user>{$user}</user>
      <password>{$pass}</password>
      <encryption>{$enc}</encryption>
      <parameters>{$paramXml}</parameters>
    </ser:{$operation}>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function soapNs(): string
    {
        return self::SOAP_NS;
    }

    /**
     * Exportar registro específico (tipoIntegracao=E) — retorna UsuGer do título.
     *
     * @return array{NumTit:string,CodEmp:int,CodFil:int,CodFor:int,CodTpt:string,UsuGer:int}|null
     */
    public function exportarEspecifico(
        int $codEmp,
        int $codFil,
        string $numTit,
        int $codFor,
        string $codTpt,
    ): ?array {
        $easy = (string) ($this->config['identificador_sistema'] ?? 'EASYTECH');
        $rows = $this->call('Exportar', [
            'identificadorSistema' => $easy,
            'tipoIntegracao' => 'E',
            'CodEmp' => $codEmp,
            'CodFil' => $codFil,
            'NumTit' => $numTit,
            'CodFor' => (string) $codFor,
            'CodTpt' => $codTpt,
        ]);

        return $rows[0] ?? null;
    }

    /**
     * ConsultarGeral por empresa/filial (página única observada ~100 registros).
     *
     * @return list<array{NumTit:string,CodEmp:int,CodFil:int,CodFor:int,CodTpt:string,UsuGer:int}>
     */
    public function consultarGeral(int $codEmp, int $codFil): array
    {
        $easy = (string) ($this->config['identificador_sistema'] ?? 'EASYTECH');

        return $this->call('ConsultarGeral', [
            'identificadorSistema' => $easy,
            'CodEmp' => $codEmp,
            'CodFil' => $codFil,
        ]);
    }

    /**
     * @return list<array{NumTit:string,CodEmp:int,CodFil:int,CodFor:int,CodTpt:string,UsuGer:int}>
     */
    public function parseTitulos(string $xml): array
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
        $temErroExec = $erroExecucao !== null && ! is_array($erroExecucao) && trim((string) $erroExecucao) !== '';
        $temErroNegocio = $this->hasBusinessError($flat);
        $msgRetorno = $this->scalarOrNull($flat['mensagemRetorno'] ?? null);
        $msgSucesso = is_string($msgRetorno) && stripos($msgRetorno, 'sucesso') !== false;

        $sucesso = ! $temErroExec && ! $temErroNegocio && (
            (string) ($tipoRetorno ?? '') === '1'
            || ((string) ($tipoRetorno ?? '') === '0' && $msgSucesso)
        );
        if (! $sucesso) {
            throw new SeniorException($this->errorMessage($flat, $erroExecucao), SeniorException::KIND_BUSINESS);
        }

        return $this->extractTitulos($flat);
    }

    /**
     * @return list<array{NumTit:string,CodEmp:int,CodFil:int,CodFor:int,CodTpt:string,UsuGer:int}>
     */
    private function call(string $operation, array $params): array
    {
        $envelope = $this->buildEnvelope($operation, $params);
        $connect = max(5, min(300, (int) ($this->config['timeout_connect'] ?? 60)));
        $response = max(5, min(300, (int) ($this->config['timeout_response'] ?? 60)));
        $maxRetries = (int) ($this->config['max_retries'] ?? 3);

        $attempt = 0;
        $backoff = [2, 4, 8];
        while (true) {
            try {
                $res = Http::withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $operation,
                ])
                    ->connectTimeout($connect)
                    ->timeout($response)
                    ->withBody($envelope, 'text/xml')
                    ->post($this->serviceUrl());

                if ($res->failed()) {
                    throw new SeniorException("Senior respondeu HTTP {$res->status()}", SeniorException::KIND_UNAVAILABLE);
                }

                return $this->parseTitulos($res->body());
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
     * @return list<array{NumTit:string,CodEmp:int,CodFil:int,CodFor:int,CodTpt:string,UsuGer:int}>
     */
    private function extractTitulos(array $flat): array
    {
        $out = [];
        $stack = [$flat];
        while ($stack) {
            $node = array_pop($stack);
            if (! is_array($node)) {
                continue;
            }

            $numTit = $node['NumTit'] ?? $node['numTit'] ?? null;
            $usuGer = $node['UsuGer'] ?? $node['usuGer'] ?? null;
            if ($numTit !== null || $usuGer !== null) {
                $codEmp = (int) (float) ($node['CodEmp'] ?? $node['codEmp'] ?? 0);
                $codFil = (int) (float) ($node['CodFil'] ?? $node['codFil'] ?? 0);
                $codFor = (int) (float) ($node['CodFor'] ?? $node['codFor'] ?? 0);
                $codTpt = trim((string) ($node['CodTpt'] ?? $node['codTpt'] ?? ''));
                $num = trim((string) $numTit);
                $ug = (int) (float) ($usuGer ?? 0);
                if ($num !== '' && $codEmp > 0 && $codFil > 0) {
                    $out[] = [
                        'NumTit' => $num,
                        'CodEmp' => $codEmp,
                        'CodFil' => $codFil,
                        'CodFor' => $codFor,
                        'CodTpt' => $codTpt,
                        'UsuGer' => $ug,
                    ];
                }
            }

            foreach ($node as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }

        return $out;
    }

    private function stripNamespaces(string $xml): string
    {
        $xml = preg_replace('/xmlns[^=]*="[^"]*"/', '', $xml) ?? $xml;

        return preg_replace('/([<\/])[a-zA-Z0-9]+:/', '$1', $xml) ?? $xml;
    }

    private function findResultNode(array $arr): array
    {
        if (isset($arr['Body']) && is_array($arr['Body'])) {
            foreach ($arr['Body'] as $node) {
                if (is_array($node) && isset($node['result']) && is_array($node['result'])) {
                    return $node['result'];
                }
                if (is_array($node)) {
                    foreach ($node as $inner) {
                        if (is_array($inner) && isset($inner['result']) && is_array($inner['result'])) {
                            return $inner['result'];
                        }
                    }
                }
            }
        }

        return $arr;
    }

    private function scalarOrNull(mixed $v): mixed
    {
        return is_array($v) ? null : $v;
    }

    private function hasBusinessError(array $flat): bool
    {
        if (! isset($flat['erros']) || ! is_array($flat['erros'])) {
            return false;
        }
        $erros = $flat['erros'];
        if (isset($erros['mensagemErro']) && trim((string) $erros['mensagemErro']) !== '') {
            return true;
        }
        foreach ($erros as $e) {
            if (is_array($e) && ! empty($e['mensagemErro'])) {
                return true;
            }
        }

        return false;
    }

    private function errorMessage(array $flat, mixed $erroExecucao): string
    {
        if ($erroExecucao !== null && ! is_array($erroExecucao) && trim((string) $erroExecucao) !== '') {
            return (string) $erroExecucao;
        }
        if (isset($flat['erros']['mensagemErro'])) {
            return (string) $flat['erros']['mensagemErro'];
        }
        if (isset($flat['mensagemRetorno']) && ! is_array($flat['mensagemRetorno'])) {
            return (string) $flat['mensagemRetorno'];
        }

        return 'Erro de negócio na Senior (prj.contaspagar)';
    }
}
