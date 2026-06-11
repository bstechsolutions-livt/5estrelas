<?php

namespace Database\Seeders;

use App\Models\Comercial\Categoria;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Indice;
use Illuminate\Database\Seeder;

/**
 * Defaults do módulo Comercial (Spec 1 — Config/Valores).
 * Valores idênticos ao protótipo "Gestão 360º". Idempotente (updateOrCreate).
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
            Escala::updateOrCreate(['nome' => $e['nome']], $e);
        }

        // Índices globais (percentuais) — defaults do protótipo
        $indices = [
            ['chave' => 'encargos', 'valor' => 82.0000, 'descricao' => 'Encargos sociais (%)'],
            ['chave' => 'administracao', 'valor' => 5.0000, 'descricao' => 'Taxa de administração (%)'],
            ['chave' => 'lucro', 'valor' => 3.0000, 'descricao' => 'Taxa de lucro (%)'],
            ['chave' => 'impostos', 'valor' => 8.6500, 'descricao' => 'Tributos (ISS+PIS+COFINS) (%)'],
            ['chave' => 'iss', 'valor' => 5.0000, 'descricao' => 'ISS (%)'],
            ['chave' => 'pis', 'valor' => 0.6500, 'descricao' => 'PIS (%)'],
            ['chave' => 'cofins', 'valor' => 3.0000, 'descricao' => 'COFINS (%)'],
        ];
        foreach ($indices as $i) {
            Indice::updateOrCreate(['chave' => $i['chave']], $i);
        }

        // Categorias profissionais (valores exatos do protótipo)
        $categorias = [
            [
                'nome' => 'Vigilante', 'icone' => 'shield', 'cor' => '#C8A84B',
                'salario_base' => 2347.80, 'periculosidade_pct' => 30, 'intrajornada_h' => 1.5, 'desconto_vt_pct' => 6,
                'plano_saude' => 242.00, 'fundo_social' => 31.50, 'sst' => 18.00, 'cna' => 22.00, 'seguro_vida' => 14.20,
                'uniforme' => 89.50, 'reciclagem' => 32.00, 'va' => 30.00, 'vt' => 10.40,
                'gta' => 47.00, 'cofre' => 55.00, 'arma' => 126.00, 'colete' => 38.00,
                'tem_arma' => true, 'tem_moto' => true,
            ],
            [
                'nome' => 'Ag. de Portaria', 'icone' => 'building', 'cor' => '#4A90D9',
                'salario_base' => 1850.00, 'periculosidade_pct' => 0, 'intrajornada_h' => 1.5, 'desconto_vt_pct' => 6,
                'plano_saude' => 180.00, 'fundo_social' => 28.00, 'sst' => 15.00, 'cna' => 18.00, 'seguro_vida' => 12.00,
                'uniforme' => 65.00, 'reciclagem' => 0, 'va' => 25.00, 'vt' => 10.40,
                'gta' => 0, 'cofre' => 0, 'arma' => 0, 'colete' => 0,
                'tem_arma' => false, 'tem_moto' => false,
            ],
            [
                'nome' => 'Limpeza', 'icone' => 'broom', 'cor' => '#4CAF7D',
                'salario_base' => 1518.00, 'periculosidade_pct' => 0, 'intrajornada_h' => 1.5, 'desconto_vt_pct' => 6,
                'plano_saude' => 160.00, 'fundo_social' => 25.00, 'sst' => 14.00, 'cna' => 16.00, 'seguro_vida' => 10.00,
                'uniforme' => 55.00, 'reciclagem' => 0, 'va' => 22.00, 'vt' => 10.40,
                'gta' => 0, 'cofre' => 0, 'arma' => 0, 'colete' => 0,
                'tem_arma' => false, 'tem_moto' => false,
            ],
            [
                'nome' => 'Bombeiro Civil', 'icone' => 'fire', 'cor' => '#E05454',
                'salario_base' => 2800.00, 'periculosidade_pct' => 30, 'intrajornada_h' => 1.5, 'desconto_vt_pct' => 6,
                'plano_saude' => 242.00, 'fundo_social' => 31.50, 'sst' => 22.00, 'cna' => 22.00, 'seguro_vida' => 18.00,
                'uniforme' => 95.00, 'reciclagem' => 45.00, 'va' => 30.00, 'vt' => 10.40,
                'gta' => 0, 'cofre' => 0, 'arma' => 0, 'colete' => 0,
                'tem_arma' => false, 'tem_moto' => false,
            ],
            [
                'nome' => 'Supervisor', 'icone' => 'star', 'cor' => '#E07A32',
                'salario_base' => 3200.00, 'periculosidade_pct' => 0, 'intrajornada_h' => 1.5, 'desconto_vt_pct' => 6,
                'plano_saude' => 242.00, 'fundo_social' => 31.50, 'sst' => 18.00, 'cna' => 22.00, 'seguro_vida' => 18.00,
                'uniforme' => 89.50, 'reciclagem' => 32.00, 'va' => 30.00, 'vt' => 10.40,
                'gta' => 0, 'cofre' => 0, 'arma' => 0, 'colete' => 0,
                'tem_arma' => false, 'tem_moto' => false,
            ],
        ];
        foreach ($categorias as $c) {
            Categoria::updateOrCreate(['nome' => $c['nome'], 'cct_id' => null], $c);
        }
    }
}
