<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Models\SeniorSupplier;

/**
 * Nome de exibição do fornecedor em títulos Senior.
 *
 * Prioridade: cadastro Senior (senior_suppliers) → nome já persistido válido →
 * padrões GFD/TRCT na observação → código genérico.
 */
class SupplierDisplayNameResolver
{
    public function isGeneric(?string $name): bool
    {
        if ($name === null || $name === '') {
            return true;
        }

        return (bool) preg_match('/^Fornecedor\s+\d+/iu', trim($name));
    }

    /**
     * Extrai nome de favorecido de folha (GFD/TRCT) da observação — nunca texto livre de parcela/ref.
     */
    public function fromDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $firstLine = trim(strtok($description, "\n") ?: $description);
        $text = trim(preg_replace('/\s+/u', ' ', $firstLine) ?? '');
        if ($text === '') {
            return null;
        }

        $patterns = [
            '/^GFD\s*-\s*(.+)$/iu',
            '/^TRCT\s*-\s*(.+)$/iu',
            '/^REF\.\s*A\s+GFD\s+(.+)$/iu',
            '/^REF\.\s*A\s+TRCT\s+(.+)$/iu',
            '/^REF\s+A\s+GFD\s+(.+)$/iu',
            '/^REF\s+A\s+TRCT\s+(.+)$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->trimName($matches[1]);
            }
        }

        return null;
    }

    public function resolveForPayable(Payable $payable, ?SeniorSupplier $supplier = null): string
    {
        if ($supplier === null && $payable->codemp && $payable->codfor) {
            $supplier = SeniorSupplier::query()
                ->where('cod_emp', (int) $payable->codemp)
                ->where('cod_for', (int) $payable->codfor)
                ->first();
        }

        if ($supplier?->name) {
            return $supplier->name;
        }

        $stored = trim((string) ($payable->supplier_name ?? ''));
        if ($stored !== '' && ! $this->isGeneric($stored) && ! $this->storedNameIsPolluted($stored, $payable->description)) {
            return $stored;
        }

        $fromDescription = $this->fromDescription($payable->description);
        if ($fromDescription) {
            return $fromDescription;
        }

        if ($payable->codfor) {
            return 'Fornecedor ' . (int) $payable->codfor;
        }

        return $stored !== '' ? $stored : 'Fornecedor não identificado';
    }

    /** Nome gravado que na verdade é observação/parcela do título. */
    public function storedNameIsPolluted(?string $name, ?string $description): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $name = trim($name);

        if ($this->looksLikeObservation($name)) {
            return true;
        }

        if ($description === null || trim($description) === '') {
            return false;
        }

        $firstLine = trim(strtok($description, "\n") ?: $description);
        $firstLine = trim(preg_replace('/\s+/u', ' ', $firstLine) ?? '');

        if ($firstLine === '') {
            return false;
        }

        if (strcasecmp($name, $firstLine) === 0) {
            return true;
        }

        return str_starts_with(mb_strtoupper($firstLine), mb_strtoupper($name))
            && mb_strlen($name) >= 12;
    }

    private function looksLikeObservation(string $text): bool
    {
        if (mb_strlen($text) > 80) {
            return true;
        }

        return (bool) preg_match(
            '/^(REFERENTE|REF\.?\s|INFRAÇÃO|INFRACAO|PLACA\s|MULTA\s|PAGAMENTO\b|PARCELA\b|REF\s+PARCELAMENTO|REF\s+A\s+LOCA)/iu',
            $text,
        );
    }

    private function trimName(string $name): string
    {
        $name = trim($name);

        return mb_strlen($name) > 120 ? mb_substr($name, 0, 117) . '...' : $name;
    }
}
