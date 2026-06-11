<?php

namespace Database\Seeders;

use App\Models\Comercial\Categoria;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Indice;
use Illuminate\Database\Seeder;

/**
 * Defaults do módulo Comercial (Spec 1 — Config/Valores).
 * Idempotente: usa firstOrCreate por chave natural.
 */
class ComercialConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Escalas comuns de vigilância
        $escalas = [
            ['nome' => '12x36 Diurno', 'dias_mes' => 15, 'horas_mes' => 180],
            ['nome' => '12x36 Noturno', 'dias_mes' => 15, 'horas_mes' => 180],
            ['nome' => '24h', 'dias_mes' => 15, 'horas_mes' => 180],
            ['nome' => '44h Semanais', 'dias_mes' => 30, 'horas_mes' => 220],
        ];
        foreach ($escalas as $e) {
            Escala::firstOrCreate(['nome' => $e['nome']], $e);
        }

        // Índices globais (percentuais)
        $indices = [
            ['chave' => 'encargos', 'valor' => 71.5000, 'descricao' => 'Encargos sociais (%)'],
            ['chave' => 'administracao', 'valor' => 8.0000, 'descricao' => 'Taxa de administração (%)'],
            ['chave' => 'lucro', 'valor' => 6.0000, 'descricao' => 'Taxa de lucro (%)'],
            ['chave' => 'impostos', 'valor' => 8.6500, 'descricao' => 'Tributos (ISS+PIS+COFINS) (%)'],
            ['chave' => 'iss', 'valor' => 5.0000, 'descricao' => 'ISS (%)'],
            ['chave' => 'pis', 'valor' => 0.6500, 'descricao' => 'PIS (%)'],
            ['chave' => 'cofins', 'valor' => 3.0000, 'descricao' => 'COFINS (%)'],
        ];
        foreach ($indices as $i) {
            Indice::firstOrCreate(['chave' => $i['chave']], $i);
        }

        // Categorias profissionais base (referência do protótipo)
        $categorias = [
            [
                'nome' => 'Vigilante', 'icone' => 'shield', 'cor' => '#2980B9',
                'salario_base' => 1900.00, 'periculosidade_pct' => 30, 'intrajornada_h' => 1.5, 'desconto_vt_pct' => 6,
                'plano_saude' => 242.00, 'fundo_social' => 31.50, 'sst' => 22.00, 'cna' => 22.00, 'seguro_vida' => 18.00,
                'uniforme' => 95.00, 'reciclagem' => 45.00, 'va' => 30.00, 'vt' => 10.40,
                'tem_arma' => false, 'tem_moto' => false,
            ],
            [
                'nome' => 'Supervisor', 'icone' => 'star', 'cor' => '#E07A32',
                'salario_base' => 3200.00, 'periculosidade_pct' => 0, 'intrajornada_h' => 1.5, 'desconto_vt_pct' => 6,
                'plano_saude' => 242.00, 'fundo_social' => 31.50, 'sst' => 18.00, 'cna' => 22.00, 'seguro_vida' => 18.00,
                'uniforme' => 89.50, 'reciclagem' => 32.00, 'va' => 30.00, 'vt' => 10.40,
                'tem_arma' => false, 'tem_moto' => false,
            ],
        ];
        foreach ($categorias as $c) {
            Categoria::firstOrCreate(['nome' => $c['nome'], 'cct_id' => null], $c);
        }
    }
}
