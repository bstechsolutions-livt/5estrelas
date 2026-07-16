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
 * fixtures, sem rede. A chamada real (consultarTitulos) usa HTTP com timeout +
 * retry + paginação por janela de vencimento.
 *
 * CONTRATO REAL validado em produção (22-23/06/2026):
 *  - codFor é OBRIGATÓRIO; consultarTitulos() consulta um único (codEmp, codFor).
 *  - Datas no formato dd/MM/yyyy (ISO é rejeitado).
 *  - tipoRetorno=1 = sucesso; qualquer outro valor (0, 2, -1) ou erroExecucao
 *    preenchido = erro de negócio.
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
     *
     * CONTRATO REAL (validado em produção 22-23/06/2026):
     *  - `tipoRetorno = 1` = sucesso ("Processado com sucesso.").
     *  - `tipoRetorno = 2` = erro de validação de negócio (mensagem em mensagemRetorno).
     *  - `tipoRetorno = 0` / `-1` = erro (ex.: web service não parametrizado; sigla
     *    não cadastrada com a mensagem em <erros><mensagemErro>).
     *  - `erroExecucao` preenchido (texto) também indica erro; o `<erroExecucao nil="true"/>`
     *    das respostas de sucesso é ignorado.
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

        // Elemento vazio/nulo (<tag xsi:nil="true"/> ou <tag/>) é decodificado como []
        // pelo json_encode(SimpleXML). Normaliza para null para não quebrar o cast (string).
        // CONTRATO REAL: fornecedor inexistente/sem serviço retorna
        // <erroExecucao>Não foi possível executar o serviço solicitado.</erroExecucao>
        // com <tipoRetorno xsi:nil="true"/> — antes isso estourava "Array to string conversion".
        $tipoRetorno = $this->scalarOrNull($flat['tipoRetorno'] ?? null);
        $erroExecucao = $flat['erroExecucao'] ?? null;

        // erroExecucao com conteúdo textual = erro de execução. O <erroExecucao nil="true"/>
        // de uma resposta de sucesso vira um array (só atributos) e é ignorado aqui.
        $temErroExec = $erroExecucao !== null && !is_array($erroExecucao) && trim((string) $erroExecucao) !== '';

        // Sucesso real: tipoRetorno == 1 e sem erroExecucao preenchido (req 1.7).
        $sucesso = ((string) ($tipoRetorno ?? '') === '1') && !$temErroExec;
        if (!$sucesso) {
            throw new SeniorException($this->errorMessage($flat, $erroExecucao), SeniorException::KIND_BUSINESS);
        }

        return ['titulos' => $this->extractTitulos($flat)];
    }

    /**
     * Elemento XML vazio ou com xsi:nil="true" vira [] no json_encode(SimpleXML).
     * Normaliza array (nil/vazio) para null; mantém escalares como estão.
     */
    private function scalarOrNull(mixed $v): mixed
    {
        return is_array($v) ? null : $v;
    }

    /**
     * Extrai a mensagem de erro mais específica da resposta da Senior.
     * Prioridade: <erros><mensagemErro> aninhado > erroExecucao textual > mensagemRetorno.
     */
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

    /** Normaliza os títulos e seus rateios para arrays associativos. */
    private function extractTitulos(array $flat): array
    {
        $raw = $flat['titulos'] ?? $flat['titulo'] ?? [];
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        // Guarda contra falso-positivo: se $raw não tem campos de título (numTit/codEmp),
        // é um nó residual do parse, não um título real.
        if (!isset($raw[0]) && !isset($raw['numTit']) && !isset($raw['codEmp'])) {
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
        // 1) Remove as declarações de namespace (xmlns e xmlns:prefixo).
        $xml = preg_replace('/\sxmlns(:[A-Za-z0-9_]+)?="[^"]*"/', '', $xml) ?? $xml;
        // 2) Remove o prefixo de namespace dos elementos (<ns2:Foo> -> <Foo>).
        $xml = preg_replace('/(<\/?)[A-Za-z0-9_]+:/', '$1', $xml) ?? $xml;
        // 3) Remove o prefixo de namespace dos atributos (xsi:nil="true" -> nil="true").
        //    Sem isso, o atributo referencia um prefixo já removido e o parser falha.
        return preg_replace('/\s[A-Za-z0-9_]+:([A-Za-z0-9_]+=)/', ' $1', $xml) ?? $xml;
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
            'retRat' => $this->config['ret_rat'] ?? 'S', // 'S' retorna rateios aninhados
        ];
        // CONTRATO REAL: datas no formato dd/MM/yyyy (ISO yyyy-MM-dd é rejeitado).
        if ($vctIni) {
            $params['vctIni'] = $vctIni->format('d/m/Y');
        }
        if ($vctFim) {
            $params['vctFim'] = $vctFim->format('d/m/Y');
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

    /**
     * Consulta os títulos abertos de UM fornecedor (codFor) numa empresa (codEmp),
     * na janela [$vctIni, $vctFim]. codFor é OBRIGATÓRIO no contrato real da Senior,
     * por isso o Payables_Sync varre codFor por empresa chamando este método.
     *
     * CONTRATO REAL (validado em produção 22-23/06/2026):
     *  - params: codEmp + codFor + retRat (+ janela vctIni/vctFim em dd/MM/yyyy).
     *  - sucesso sem títulos (fornecedor sem títulos em aberto) retorna [] sem erro.
     *
     * @return array lista de títulos (cada um com 'rateios')
     */
    public function consultarTitulosPorFornecedor(int $codEmp, int $codFor, ?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $params = [
            'codEmp' => $codEmp,
            'codFor' => $codFor,
            'retRat' => $this->config['ret_rat'] ?? 'N',
        ];
        // CONTRATO REAL: datas no formato dd/MM/yyyy (ISO yyyy-MM-dd é rejeitado).
        if ($vctIni) {
            $params['vctIni'] = $vctIni->format('d/m/Y');
        }
        if ($vctFim) {
            $params['vctFim'] = $vctFim->format('d/m/Y');
        }

        return $this->callOnce($params);
    }

    /**
     * Consulta todos os títulos abertos de uma empresa em uma chamada (todas as filiais).
     * Requer CliOpcAbr (F000PGS). Params: codEmp + retRat (+ janela); codFor/codFil omitidos.
     * Validado em PRD 16/07/2026: emp 2 sem filial → 1386 títulos (filiais 1–6).
     *
     * @return array lista de títulos (cada um com 'rateios')
     */
    public function consultarTitulosAbertosPorEmpresa(int $codEmp, ?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $params = [
            'codEmp' => $codEmp,
            'retRat' => $this->config['ret_rat'] ?? 'N',
        ];
        if ($vctIni) {
            $params['vctIni'] = $vctIni->format('d/m/Y');
        }
        if ($vctFim) {
            $params['vctFim'] = $vctFim->format('d/m/Y');
        }

        return $this->callOnce($params, $this->bulkEmpresaTimeout());
    }

    /**
     * Consulta títulos abertos de uma empresa/filial (bulk estreito).
     * Preferir consultarTitulosAbertosPorEmpresa no sync — menos round-trips.
     *
     * @return array lista de títulos (cada um com 'rateios')
     */
    public function consultarTitulosAbertosPorEmpresaFilial(int $codEmp, int $codFil, ?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $params = [
            'codEmp' => $codEmp,
            'codFil' => $codFil,
            'retRat' => $this->config['ret_rat'] ?? 'N',
        ];
        if ($vctIni) {
            $params['vctIni'] = $vctIni->format('d/m/Y');
        }
        if ($vctFim) {
            $params['vctFim'] = $vctFim->format('d/m/Y');
        }

        return $this->callOnce($params, (int) (
            $this->config['sync_http_timeout']
            ?? $this->config['cp_timeout_response']
            ?? $this->config['timeout_response']
            ?? 60
        ));
    }

    /** Timeout SOAP do bulk por empresa (payload maior; default 180s). */
    private function bulkEmpresaTimeout(): int
    {
        return (int) (
            $this->config['sync_http_timeout_bulk_empresa']
            ?? $this->config['sync_http_timeout']
            ?? $this->config['cp_timeout_response']
            ?? $this->config['timeout_response']
            ?? 180
        );
    }

    /** Uma chamada HTTP com timeout + retry (backoff 2/4/8s). */
    private function callOnce(array $params, ?int $responseTimeout = null): array
    {
        $envelope = $this->buildEnvelope($params);
        $connect = $this->clamp((int) ($this->config['timeout_connect'] ?? 60), 5, 300);
        $response = $this->clamp($responseTimeout ?? (int) ($this->config['timeout_response'] ?? 60), 5, 300);
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
