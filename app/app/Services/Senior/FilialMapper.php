<?php

namespace App\Services\Senior;

/**
 * Mapeia uma filial retornada pela Senior (cad_filial / ConsultarGeral) para as
 * colunas de bs_comercial_filiais.
 *
 * NÃO inclui os campos de apresentação local (tipo/tag) — esses são preservados
 * no upsert do FiliaisSyncService (analogamente aos Workflow_Fields dos payables).
 */
class FilialMapper
{
    /** Business key Senior: "codEmp-codFil". Null se faltar codEmp. */
    public function businessKey(array $filial): ?string
    {
        $codEmp = $filial['codEmp'] ?? null;
        if ($codEmp === null || $codEmp === '') {
            return null;
        }
        $codFil = $filial['codFil'] ?? 1;

        return trim((string) $codEmp) . '-' . trim((string) ($codFil === '' ? 1 : $codFil));
    }

    /** Colunas de origem Senior (nome/fantasia/cnpj/uf/cod_emp/cod_fil + bruto). */
    public function mapHeader(array $filial): array
    {
        $nome = $this->str($filial['nenFil'] ?? null)      // razão social (nome empresarial)
            ?? $this->str($filial['nomFil'] ?? null)        // nome da filial
            ?? 'Empresa ' . ($filial['codEmp'] ?? '?');

        return [
            'cod_emp' => $this->int($filial['codEmp'] ?? null),
            'cod_fil' => $this->int($filial['codFil'] ?? null) ?? 1,
            'nome' => $nome,
            'fantasia' => $this->str($filial['nomFil'] ?? null),
            'cnpj' => $this->str($filial['numCgc'] ?? null),
            'uf' => $this->str($filial['sigUfs'] ?? null),
            'senior_raw' => $filial,
        ];
    }

    private function str(mixed $v): ?string
    {
        if ($v === null || is_array($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function int(mixed $v): ?int
    {
        if ($v === null || $v === '' || is_array($v)) {
            return null;
        }

        return (int) $v;
    }
}
