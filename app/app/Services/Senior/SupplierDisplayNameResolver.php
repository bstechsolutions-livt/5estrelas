<?php

namespace App\Services\Senior;

/**
 * Resolve nomes de exibição quando cad_fornecedor não tem o codFor
 * (ex.: GFD, TRCT, VT — favorecidos de folha com nome em obsTcp).
 */
class SupplierDisplayNameResolver
{
    public function isGeneric(?string $name): bool
    {
        return $name !== null && str_starts_with($name, 'Fornecedor ');
    }

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
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->trimName($matches[1]);
            }
        }

        $firstLine = trim(strtok($text, "\n") ?: $text);
        if ($firstLine === '') {
            return null;
        }

        return $this->trimName($firstLine);
    }

    private function trimName(string $name): string
    {
        $name = trim($name);

        return mb_strlen($name) > 120 ? mb_substr($name, 0, 117) . '...' : $name;
    }
}
