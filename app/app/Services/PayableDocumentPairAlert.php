<?php

namespace App\Services;

use App\Models\Payable;
use App\Models\PayableDocument;
use Illuminate\Support\Collection;

class PayableDocumentPairAlert
{
    public const MISSING_NOTA = 'missing_nota';

    public const MISSING_BOLETO = 'missing_boleto';

    /** Status em que o alerta de par boleto/NF é exibido. */
    public const ALERT_STATUSES = ['pendente', 'em_preparacao'];

    /**
     * @return array{code: string, message: string}|null
     */
    public static function resolve(bool $hasBoleto, bool $hasNota): ?array
    {
        if ($hasBoleto && ! $hasNota) {
            return [
                'code' => self::MISSING_NOTA,
                'message' => 'Boleto anexado, mas falta a nota fiscal.',
            ];
        }

        if ($hasNota && ! $hasBoleto) {
            return [
                'code' => self::MISSING_BOLETO,
                'message' => 'Nota fiscal anexada, mas falta o boleto.',
            ];
        }

        return null;
    }

    /**
     * @param iterable<Payable> $payables
     */
    public static function attachToPayables(iterable $payables): void
    {
        $items = collect($payables)->filter(fn (Payable $p) => in_array($p->status, self::ALERT_STATUSES, true));
        if ($items->isEmpty()) {
            return;
        }

        $ids = $items->pluck('id');
        $counts = PayableDocument::query()
            ->whereIn('payable_id', $ids)
            ->whereIn('doc_type', ['boleto', 'nota_fiscal'])
            ->selectRaw('payable_id, doc_type, count(*) as total')
            ->groupBy('payable_id', 'doc_type')
            ->get()
            ->groupBy('payable_id');

        foreach ($items as $payable) {
            $byType = ($counts->get($payable->id) ?? collect())->keyBy('doc_type');
            $hasBoleto = (int) ($byType->get('boleto')?->total ?? 0) > 0;
            $hasNota = (int) ($byType->get('nota_fiscal')?->total ?? 0) > 0;
            $payable->setAttribute('document_pair_alert', self::resolve($hasBoleto, $hasNota));
        }
    }

    /** @param Collection<int, PayableDocument> $documents */
    public static function resolveFromDocuments(Collection $documents, string $status): ?array
    {
        if (! in_array($status, self::ALERT_STATUSES, true)) {
            return null;
        }

        $hasBoleto = $documents->contains(fn (PayableDocument $d) => $d->doc_type === 'boleto');
        $hasNota = $documents->contains(fn (PayableDocument $d) => $d->doc_type === 'nota_fiscal');

        return self::resolve($hasBoleto, $hasNota);
    }
}
