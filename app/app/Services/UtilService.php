<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Utilitários portados (parcial) da intranet Biglar.
 * exportToExcel: gera CSV (UTF-8 BOM) com a mesma assinatura usada pelos
 * controllers de contratos. CSV abre direto no Excel. Trocar por xlsx real
 * (maatwebsite/excel) numa spec dedicada, se necessário.
 *
 * $colunas: array de ['nome' => 'campo', 'label' => 'Cabeçalho']
 */
class UtilService
{
    public function exportToExcel($dados, array $colunas, string $nomeArquivo = 'relatorio_'): StreamedResponse
    {
        $nomeCompleto = $nomeArquivo . date('Y-m-d_H-i-s') . '.csv';
        $dados = collect($dados);

        return new StreamedResponse(function () use ($dados, $colunas) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para acentos abrirem corretos no Excel
            fwrite($out, "\xEF\xBB\xBF");

            // Cabeçalhos
            fputcsv($out, collect($colunas)->pluck('label')->toArray(), ';');

            foreach ($dados as $item) {
                $linha = [];
                foreach ($colunas as $coluna) {
                    $campo = $coluna['nome'];
                    $valor = is_object($item) ? data_get($item, $campo) : ($item[$campo] ?? '');
                    if (is_array($valor) || is_object($valor)) {
                        $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
                    }
                    $linha[] = $valor;
                }
                fputcsv($out, $linha, ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nomeCompleto . '"',
        ]);
    }
}
