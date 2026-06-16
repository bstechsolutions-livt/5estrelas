<?php

namespace App\Services\Senior;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Senior_CP_Client (requirements 1 e 2): consulta read-only de títulos a pagar
 * na Senior via webservice SOAP (operação ConsultarTitulosAbertosCP).
 *
 * Apenas operações de consulta (req 1.5). A montagem do envelope (buildEnvelope)
 * e o parse da resposta (parseResponse) são públicos para teste unitário com
 * fixtures, sem rede. A chamada real (consultarTitulosAbertos) usa HTTP com
 * timeout + retry + paginação por janela de vencimento.
 */
class SeniorCpClient
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
        return rtrim($this->endpointBase(), '/') . '/' . $this->config['cp_service'];
    }

    /**
     * Remove caracteres de controle (0–31, exceto \t \n \r) dos parâmetros (req 2.5).
     */
    private function sanitize(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    }

    /** Monta o envelope SOAP de ConsultarTitulosAbertosCP (req 1.1, 1.2). */
    public function buildEnvelope(array $params): string
    {
        $cred = $this->config['credentials'];
        $user = $this->sanitize((string) ($cred['user'] ?? ''));
        $pass = $this->sanitize((string) ($cred['password'] ?? ''));
        $enc = $this->sanitize((string) ($cred['encryption'] ?? '0'));

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
    <ser:ConsultarTitulosAbertosCP>
      <user>{$this->esc($user)}</user>
      <password>{$this->esc($pass)}</password>
      <encryption>{$this->esc($enc)}</encryption>
      <parameters>{$paramXml}</parameters>
    </ser:ConsultarTitulosAbertosCP>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function soapNs(): string
    {
        return self::SOAP_NS;
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1);
    }

    /**
     * Faz o parse da resposta SOAP em ['titulos' => [...]].
     * Detecta erro de negócio (tipoRetorno/erroExecucao/mensagemRetorno) e lança
     * SeniorException de negócio (req 1.7).
     */
    public function parseResponse(string $xml): array
    {
        $clean = $this->stripNamespaces($xml);
        $sx = @simplexml_load_string($clean);
        if ($sx === false) {
            throw new SeniorException('Resposta SOAP inválida (XML não pôde ser lido)', SeniorException::KIND_UNAVAILABLE);
        }

        $arr = json_decode(json_encode($sx), true) ?: [];
        $flat = $this->findResultNode($arr);

        // Erro de negócio retornado pela Senior (req 1.7).
        $tipoRetorno = $flat['tipoRetorno'] ?? null;
        $erroExecucao = $flat['erroExecucao'] ?? null;
        $isErro = ($tipoRetorno !== null && !in_array(strtolower((string) $tipoRetorno), ['0', 'ok', 'sucesso', 'success'], true))
            || ($erroExecucao !== null && trim((string) $erroExecucao) !== '' && $erroExecucao !== []);
        if ($isErro) {
            $msg = trim((string) ($flat['mensagemRetorno'] ?? $erroExecucao ?? 'Erro retornado pela Senior'));

            throw new SeniorException($msg !== '' ? $msg : 'Erro retornado pela Senior', SeniorException::KIND_BUSINESS);
        }

        return ['titulos' => $this->extractTitulos($flat)];
    }

    /** Normaliza os títulos e seus rateios para arrays associativos. */
    private function extractTitulos(array $flat): array
    {
        $raw = $flat['titulos'] ?? $flat['titulo'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        // Um único título vem como assoc; vários vêm como lista.
        if (array_keys($raw) !== range(0, count($raw) - 1)) {
            $raw = [$raw];
        }

        return array_map(function ($titulo) {
            if (!is_array($titulo)) {
                return [];
            }
            if (isset($titulo['rateios'])) {
                $rt = $titulo['rateios']['rateio'] ?? $titulo['rateios'] ?? [];
                if (is_array($rt) && array_keys($rt) !== range(0, max(0, count($rt) - 1))) {
                    $rt = [$rt];
                }
                $titulo['rateios'] = is_array($rt) ? array_values($rt) : [];
            } else {
                $titulo['rateios'] = [];
            }

            return $titulo;
        }, array_values($raw));
    }

    private function stripNamespaces(string $xml): string
    {
        // Remove prefixos de namespace dos elementos para simplificar o parse.
        $xml = preg_replace('/(<\/?)[A-Za-z0-9_]+:/', '$1', $xml);

        return preg_replace('/\sxmlns(:[A-Za-z0-9_]+)?="[^"]*"/', '', $xml) ?? $xml;
    }

    private function findResultNode(array $arr): array
    {
        // Procura recursivamente um nó que tenha 'titulos'/'titulo' ou campos de retorno.
        $markers = ['titulos', 'titulo', 'tipoRetorno', 'mensagemRetorno', 'erroExecucao'];
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
     * Consulta os títulos abertos na janela [$vctIni, $vctFim] (req 1, 2, 5).
     * Pagina por janela quando o volume satura o lote (req 1.8). Aplica timeout e
     * retry com backoff (req 1.9, 1.10, 2.1, 2.4). Lança SeniorException em falha.
     *
     * @return array lista de títulos (cada um com 'rateios')
     */
    public function consultarTitulosAbertos(?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $params = [
            'codEmp' => $this->config['cod_emp'] ?? 1,
            'retRat' => 'S', // retorna rateios aninhados
        ];
        if ($vctIni) {
            $params['vctIni'] = $vctIni->format('Y-m-d');
        }
        if ($vctFim) {
            $params['vctFim'] = $vctFim->format('Y-m-d');
        }

        $titulos = $this->callOnce($params);

        // Paginação por janela: se saturou o lote, divide a janela e recursa (req 1.8).
        $batch = (int) ($this->config['batch_size'] ?? 500);
        if (count($titulos) >= $batch && $vctIni && $vctFim && $vctIni->lt($vctFim)) {
            $meio = $vctIni->copy()->addDays((int) ($vctIni->diffInDays($vctFim) / 2));
            $a = $this->consultarTitulosAbertos($vctIni, $meio);
            $b = $this->consultarTitulosAbertos($meio->copy()->addDay(), $vctFim);

            return $this->dedup(array_merge($a, $b));
        }

        return $titulos;
    }

    /** Uma chamada HTTP com timeout + retry (backoff 2/4/8s). */
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
                    throw new SeniorException(
                        "Senior respondeu HTTP {$res->status()}",
                        SeniorException::KIND_UNAVAILABLE,
                    );
                }

                return $this->parseResponse($res->body())['titulos'];
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

    /** Remove títulos duplicados pela Business_Key (req 1.8). */
    private function dedup(array $titulos): array
    {
        $mapper = new PayableMapper();
        $seen = [];
        $out = [];
        foreach ($titulos as $t) {
            $key = $mapper->businessKey($t) ?? json_encode($t);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $t;
            }
        }

        return $out;
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
