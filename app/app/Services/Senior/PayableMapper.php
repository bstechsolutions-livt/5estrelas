<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Models\PayableRateio;
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
            $v = $titulo[$k] ?? null;
            if ($v === null || $v === '') {
                return null;
            }
            $parts[] = trim((string) $v);
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
        $out['title_number'] = isset($titulo['numTit']) ? (string) $titulo['numTit'] : null;
        $out['amount'] = $this->convert($titulo['vlrOri'] ?? null, 'money') ?? 0;
        $out['due_date'] = $this->convert($titulo['vctPro'] ?? $titulo['vctOri'] ?? null, 'date');
        $out['issue_date'] = $this->convert($titulo['datEmi'] ?? null, 'date');
        $out['supplier_name'] = $this->supplierName($titulo);
        $out['supplier_cnpj'] = isset($titulo['docIdeFav']) ? (string) $titulo['docIdeFav'] : null;
 $out["description"] = isset($titulo["obsTcp"]) ? (string) $titulo["obsTcp"] : null;
 $out["category"] = isset($titulo["codTns"]) ? "Transação " . $titulo["codTns"] : null;

        // Metadados de origem.
        $out['senior_situacao_original'] = isset($titulo['sitTit']) ? (string) $titulo['sitTit'] : null;
        $out['senior_raw'] = $titulo;

        return $out;
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

    /** Nome de exibição do fornecedor (a Senior CP não retorna o nome, só o código). */
    private function supplierName(array $titulo): string
    {
        $doc = trim((string) ($titulo['docIdeFav'] ?? ''));
        if ($doc !== '') {
            return $doc;
        }
        $codFor = $titulo['codFor'] ?? null;

        return $codFor !== null && $codFor !== '' ? 'Fornecedor ' . $codFor : 'Fornecedor não identificado';
    }

    /** Converte um valor cru da Senior pelo tipo lógico; null em falha/ausência. */
    private function convert(mixed $value, string $type): mixed
    {
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
