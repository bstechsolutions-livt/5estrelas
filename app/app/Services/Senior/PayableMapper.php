<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Models\PayableRateio;
use App\Models\SeniorSupplier;
use Carbon\Carbon;

/**
 * Payable_Mapper (requirement 3): mapeia um título retornado pela Senior
 * (ConsultarTitulosAbertosCP v3) para as colunas de `payables`/`payable_rateios`.
 *
 * - Converte dinheiro → decimal(2) e datas → date (req 3.5).
 * - Campo ausente/nulo → coluna nula, sem interromper os demais (req 3.7).
 * - Falha de conversão → coluna nula, original preservado em senior_raw (req 3.8).
 * - Conteúdo bruto sempre guardado em senior_raw (req 3.4).
 */
class PayableMapper
{
    /**
     * Deriva a Business_Key (codEmp-codFil-numTit-codTpt-codFor).
     * Retorna null quando algum componente essencial está ausente (req 4.7).
     */
    public function businessKey(array $titulo): ?string
    {
        $parts = [];
        foreach (['codEmp', 'codFil', 'numTit', 'codTpt', 'codFor'] as $k) {
            $v = $this->asString($titulo[$k] ?? null);
            if ($v === null || $v === '') {
                return null;
            }
            $parts[] = trim($v);
        }

        return implode('-', $parts);
    }

    /**
     * Mapeia os campos de cabeçalho + campos de exibição + metadados de origem.
     * Retorna [coluna => valor]; NÃO inclui Workflow_Fields.
     */
    public function mapHeader(array $titulo): array
    {
        $out = [];

        foreach (Payable::seniorHeaderFields() as $code => $type) {
            $col = Payable::seniorColumn($code);
            $out[$col] = $this->convert($titulo[$code] ?? null, $type);
        }

        // Campos de exibição da tela (também origem Senior).
        $out['title_number'] = $this->asString($titulo['numTit'] ?? null);
        $out['amount'] = $this->convert($titulo['vlrOri'] ?? null, 'money') ?? 0;
        $out['due_date'] = $this->convert($titulo['vctPro'] ?? $titulo['vctOri'] ?? null, 'date');
        $out['issue_date'] = $this->convert($titulo['datEmi'] ?? null, 'date');
        $out['supplier_name'] = $this->supplierName($titulo);
        $out['supplier_cnpj'] = $this->asString($titulo['docIdeFav'] ?? null);
        $out['description'] = $this->asString($titulo['obsTcp'] ?? null);
        $codTns = $this->asString($titulo['codTns'] ?? null);
        $out['category'] = $codTns !== null ? 'Transação ' . $codTns : null;

        // Usuário que lançou o título na Senior (codUsu/codFav quando > 0).
        $out['senior_cod_usu'] = self::resolveLauncherCodUsu($titulo);

        // Metadados de origem.
        $out['senior_situacao_original'] = $this->asString($titulo['sitTit'] ?? null);
        $out['senior_raw'] = $titulo;

        return $out;
    }

    /**
     * Extrai o código do usuário lançador do título Senior.
     * Preferência: UsuGer (prj.contaspagar / E501TCP) → codUsu → codFav.
     * ConsultarTitulosAbertosCP não traz UsuGer; AbertosCP em produção costuma vir com codFav=0.
     */
    public static function resolveLauncherCodUsu(array $titulo): ?int
    {
        foreach (['UsuGer', 'usuGer', 'codUsu', 'codFav'] as $key) {
            $raw = $titulo[$key] ?? null;
            if ($raw === null || $raw === '' || (is_array($raw) && $raw === [])) {
                continue;
            }
            if (is_array($raw)) {
                $raw = reset($raw);
            }
            $n = is_numeric($raw) ? (int) (float) $raw : null;
            if ($n !== null && $n > 0) {
                return $n;
            }
        }

        return null;
    }

    /** Mapeia um rateio (Apêndice A.3) para colunas de payable_rateios. */
    public function mapRateio(array $rateio): array
    {
        $out = [];
        foreach (PayableRateio::SENIOR_FIELDS as $code => $type) {
            $out[PayableRateio::seniorColumn($code)] = $this->convert($rateio[$code] ?? null, $type);
        }

        return $out;
    }

    /** Nome de exibição do fornecedor (cache local, descrição ou docIdeFav da Senior). */
    private function supplierName(array $titulo): string
    {
        $codEmp = isset($titulo['codEmp']) ? (int) $titulo['codEmp'] : null;
        $codFor = $titulo['codFor'] ?? null;
        if ($codEmp && $codFor !== null && $codFor !== '') {
            $cached = SeniorSupplier::resolveName($codEmp, $codFor);
            if ($cached) {
                return $cached;
            }
        }

        $fromDescription = (new SupplierDisplayNameResolver())->fromDescription(
            $this->asString($titulo['obsTcp'] ?? null),
        );
        if ($fromDescription) {
            return $fromDescription;
        }

        $doc = trim($this->asString($titulo['docIdeFav'] ?? null) ?? '');
        if ($doc !== '') {
            return $doc;
        }

        return $codFor !== null && $codFor !== '' ? 'Fornecedor ' . $codFor : 'Fornecedor não identificado';
    }

    /**
     * Elemento XML vazio ou com xsi:nil="true" vira [] no json_encode(SimpleXML).
     * Normaliza arrays antes da conversão tipada (mesmo padrão de SeniorCpClient::scalarOrNull).
     */
    private function normalizeScalar(mixed $value, bool $forString = false): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($value === []) {
            return null;
        }
        if (count($value) === 1) {
            return $this->normalizeScalar(reset($value), $forString);
        }

        return $forString
            ? implode('; ', array_map('strval', $value))
            : null;
    }

    private function asString(mixed $value): ?string
    {
        $normalized = $this->normalizeScalar($value, true);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        return (string) $normalized;
    }

    /** Converte um valor cru da Senior pelo tipo lógico; null em falha/ausência. */
    private function convert(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $forString = !in_array($type, ['money', 'rate', 'date', 'int'], true);
        $value = $this->normalizeScalar($value, $forString);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return match ($type) {
                'money', 'rate' => $this->parseNumber($value),
                'date' => $this->parseDate($value),
                'int' => (int) $value,
                default => (string) $value,
            };
        } catch (\Throwable) {
            // req 3.8: original já está preservado em senior_raw; coluna tipada fica nula.
            return null;
        }
    }

    /** Aceita "1234.56" e "1.234,56"; retorna float ou lança em valor inválido. */
    private function parseNumber(mixed $value): float
    {
        if (is_array($value)) {
            throw new \InvalidArgumentException('valor numérico inválido: array');
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);

        // Formato brasileiro "1.234,56" → "1234.56".
        if (str_contains($s, ',') && (strrpos($s, ',') > strrpos($s, '.') || !str_contains($s, '.'))) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        if (!is_numeric($s)) {
            throw new \InvalidArgumentException("valor numérico inválido: {$value}");
        }

        return (float) $s;
    }

    /** Tenta múltiplos formatos de data da Senior; lança em valor inválido. */
    private function parseDate(mixed $value): string
    {
        if (is_array($value)) {
            throw new \InvalidArgumentException('data inválida: array');
        }
        $s = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'Y-m-d\TH:i:s', 'd/m/Y H:i:s', 'dmY', 'Ymd'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $s);
            } catch (\Throwable) {
                continue;
            }
            if ($dt !== false && $dt->format($fmt) === $s) {
                return $dt->toDateString();
            }
        }

        // Última tentativa: parser flexível do Carbon (lança se inválido).
        return Carbon::parse($s)->toDateString();
    }
}
