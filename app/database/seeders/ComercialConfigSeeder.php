<?php

namespace Database\Seeders;

use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Encargo;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Filial;
use App\Models\Comercial\Indice;
use App\Models\Comercial\Insumo;
use Illuminate\Database\Seeder;

/**
 * Defaults do módulo Comercial (Spec 1 — Config/Valores).
 * Valores idênticos ao protótipo "Gestão 360º". Idempotente (updateOrCreate).
 */
class ComercialConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Escalas (valores e config do protótipo)
        // Remove nomes antigos (versão inicial) para não duplicar
        Escala::whereIn('nome', ['12x36 Diurno', '12x36 Noturno', '24h', '44h Semanais'])->delete();
        $escalas = [
            ['nome' => '12x36 — Diurno', 'dias_mes' => 15.5, 'horas_mes' => 220, 'qtd_diurno' => 2, 'qtd_noturno' => 0, 'func_por_posto' => 2, 'tem_an' => false, 'jornada' => '07h00 às 19h00'],
            ['nome' => '12x36 — Noturno', 'dias_mes' => 15.5, 'horas_mes' => 220, 'qtd_diurno' => 0, 'qtd_noturno' => 2, 'func_por_posto' => 2, 'tem_an' => true, 'jornada' => '19h00 às 07h00'],
            ['nome' => '24 Horas (12x36)', 'dias_mes' => 15.5, 'horas_mes' => 220, 'qtd_diurno' => 2, 'qtd_noturno' => 2, 'func_por_posto' => 4, 'tem_an' => true, 'jornada' => '07h00 às 07h00 (ininterrupto)'],
            ['nome' => '44h — 5×2', 'dias_mes' => 22, 'horas_mes' => 220, 'qtd_diurno' => 1, 'qtd_noturno' => 0, 'func_por_posto' => 1, 'tem_an' => false, 'jornada' => '08h/dia · Seg a Sex (ou Seg a Sab)'],
            ['nome' => '44h — 6×1', 'dias_mes' => 26, 'horas_mes' => 220, 'qtd_diurno' => 1, 'qtd_noturno' => 0, 'func_por_posto' => 1, 'tem_an' => false, 'jornada' => '7h20/dia · Seg a Sab'],
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

        $this->seedCcts();
        $this->seedEncargos();
        $this->seedInsumos();
        $this->seedFiliais();
    }

    /**
     * Filiais / Empresas do grupo — ESPELHADAS da Senior (mapa F000EMP, codEmp).
     * Fonte da verdade é a Senior; este seed reflete as empresas reais já
     * confirmadas (codEmp 2..12) enquanto o web service cad_filial não está
     * liberado. O sync `senior:sync-filiais` atualiza/completa quando disponível.
     * Idempotente por cod_emp (senior_id = "codEmp-1").
     */
    private function seedFiliais(): void
    {
        // [cod_emp, razão social (nome), fantasia, tipo, cnpj(null até sync)]
        // Ramos: segurança/vigilância (2,9,10,11) = 'seguranca'; demais = 'apoio'.
        $rows = [
            [2, '5 ESTRELAS SISTEMA DE SEGURANCA LTDA', '5 ESTRELAS', 'seguranca'],
            [3, '5 ESTRELAS SERVICOS DE APOIO ADMINISTRATIVO LTDA', 'SERV APOIO', 'apoio'],
            [4, 'ARI CONSTRUTORA E ADMINISTRADORA LTDA', 'ARI ADM', 'apoio'],
            [5, '5 ESTRELAS REFEICOES COLETIVAS', 'REFEICOES', 'apoio'],
            [6, '5 ESTRELAS SERVICOS ESPECIALIZADOS', 'SRV ESPEC', 'apoio'],
            [7, 'BEST SERVICE - ADMINISTRACAO E EVENTOS EMPRESARIAIS LTDA', 'BEST', 'apoio'],
            [8, 'SS SERVICOS DE MANUTENCAO E LIMPEZA LTDA', 'SS SRV', 'apoio'],
            [9, 'BALUARTE VIGILANCIA PATRIMONIAL LTDA', 'BALUARTE', 'seguranca'],
            [10, 'MULTI SEGURANCA ELETRONICA E PATRIMONIAL LTDA', 'MULTI', 'seguranca'],
            [11, 'STAR SEGURANCA ELETRONICA LTDA', 'STAR', 'seguranca'],
            [12, 'LSR INCORPORADORA, CONSTRUTORA E IMOBILIARIA EIRELI', 'LSR', 'apoio'],
        ];
        $ordem = 0;
        foreach ($rows as $r) {
            [$codEmp, $nome, $fantasia, $tipo] = $r;
            Filial::updateOrCreate(
                ['senior_id' => $codEmp . '-1'],
                [
                    'cod_emp' => $codEmp,
                    'cod_fil' => 1,
                    'nome' => $nome,
                    'fantasia' => $fantasia,
                    'tag' => $fantasia,
                    'tipo' => $tipo,
                    'ativo' => true,
                    'ordem' => $ordem++,
                ],
            );
        }
    }

    /** Insumos globais (12 itens) — defaults do protótipo. */
    private function seedInsumos(): void
    {
        $itens = [
            ['uniforme', 'Uniforme', 89.50],
            ['epi', 'EPI', 0],
            ['colete', 'Colete', 0],
            ['reciclag', 'Reciclagem', 17.58],
            ['treinamento', 'Treinamento', 0],
            ['aso', 'ASO — Atestado de Saúde Ocupacional', 0],
            ['gta', 'GTA — Guia de Tráfego de Armas', 47.00],
            ['cofre', 'Cofre', 55.00],
            ['arma', 'Armamento', 126.00],
            ['guarita', 'Guarita', 0],
            ['radio', 'Rádio', 0],
            ['moto', 'Motocicleta', 0],
        ];
        $ordem = 0;
        foreach ($itens as $i) {
            Insumo::updateOrCreate(
                ['chave' => $i[0]],
                ['label' => $i[1], 'valor' => $i[2], 'ordem' => $ordem++],
            );
        }
    }

    /** Encargos sociais detalhados (grupos A/B/C/D) — valores do protótipo. */
    private function seedEncargos(): void
    {
        // [grupo, codigo, label, percentual]
        $itens = [
            ['A', 'a01', 'INSS — Previdência Social', 20.00],
            ['A', 'a02', 'FGTS', 8.00],
            ['A', 'a03', 'Salário Educação', 2.50],
            ['A', 'a04', 'SESI', 1.50],
            ['A', 'a05', 'SENAI', 1.00],
            ['A', 'a06', 'INCRA', 0.20],
            ['A', 'a07', 'Seguro de Acidente de Trabalho (RAT × FAP)', 2.11],
            ['A', 'a08', 'SEBRAE', 0.60],
            ['B', 'b01', '13º Salário', 8.93],
            ['B', 'b02', 'Férias', 9.09],
            ['B', 'b03', 'Abono de Férias (1/3)', 3.03],
            ['B', 'b04', 'Auxílio Doença (reposição)', 2.85],
            ['B', 'b05', 'Licença Paternidade / Maternidade', 0.85],
            ['B', 'b06', 'Faltas Legais', 2.38],
            ['B', 'b07', 'Acidente de Trabalho (15 dias INSS)', 0.75],
            ['B', 'b08', 'Aviso Prévio Indenizado (reposição)', 2.37],
            ['C', 'c01', 'Aviso Prévio Trabalhado', 1.85],
            ['C', 'c02', 'Indenização Adicional', 0.22],
            ['C', 'c03', 'Multa FGTS — Rescisão sem Justa Causa (40% + 10%)', 4.00],
            ['D', 'd01', 'Incidência dos encargos do Grupo A sobre os Grupos B e C', 9.77],
        ];
        $ordem = 0;
        foreach ($itens as $i) {
            Encargo::updateOrCreate(
                ['codigo' => $i[1]],
                ['grupo' => $i[0], 'label' => $i[2], 'percentual' => $i[3], 'ordem' => $ordem++],
            );
        }

        // Sincroniza o índice "encargos" com o somatório do detalhamento
        Indice::updateOrCreate(
            ['chave' => 'encargos'],
            ['valor' => Encargo::totalGeral(), 'descricao' => 'Encargos sociais (%)'],
        );
    }

    /**
     * 20 CCTs (5 estados × 4 serviços) com valores do protótipo (CCT_DEFAULTS + CCT_META).
     */
    private function seedCcts(): void
    {
        // [uf, servico, titulo, sindicato, sal, horas_mes, dias_mes, peric, an, intra,
        //  saude, fundo, sst, cna, seguro, uniforme, reciclag, va, vt, desc_vt, gta, cofre, arma, colete]
        $rows = [
            ['df', 'vigilancia', 'CCT Vigilância — DF', 'SINDESV/DF × SIEMACO', 2347.80, 220, 15.5, 30, 20, 1.5, 242.00, 31.50, 18.00, 22.00, 14.20, 89.50, 32.00, 30.00, 10.40, 6, 47.00, 55.00, 126.00, 38.00],
            ['df', 'portaria', 'CCT Portaria e Recepção — DF', 'FETHE/DF × STAS', 2029.22, 220, 22, 0, 0, 1.0, 209.40, 14.28, 15.00, 1.33, 3.78, 65.00, 0, 46.38, 10.40, 6, 0, 0, 0, 0],
            ['df', 'limpeza', 'CCT Limpeza e Conservação — DF', 'FENASERHTT/DF × SIEMACO', 1862.09, 220, 22, 0, 0, 1.0, 209.40, 14.28, 15.00, 1.33, 3.78, 55.00, 0, 46.38, 10.40, 6, 0, 0, 0, 0],
            ['df', 'bombeiro', 'CCT Bombeiro Civil — DF', 'SINDESV/DF', 2800.00, 220, 15.5, 30, 0, 1.5, 242.00, 31.50, 22.00, 22.00, 18.00, 95.00, 45.00, 30.00, 10.40, 6, 0, 0, 0, 0],
            ['go', 'vigilancia', 'CCT Vigilância — GO', 'SINDESV/GO × SIEMACO-GO', 2280.00, 220, 15.5, 30, 20, 1.5, 220.00, 30.00, 16.00, 20.00, 13.00, 85.00, 30.00, 28.00, 9.80, 6, 45.00, 50.00, 120.00, 35.00],
            ['go', 'portaria', 'CCT Portaria e Recepção — GO', 'FETHE/GO', 1780.00, 220, 15.5, 0, 0, 1.5, 170.00, 26.00, 14.00, 17.00, 11.00, 60.00, 0, 23.00, 9.80, 6, 0, 0, 0, 0],
            ['go', 'limpeza', 'CCT Limpeza e Conservação — GO', 'FENASERHTT/GO', 1450.00, 220, 22, 0, 0, 1.0, 150.00, 23.00, 13.00, 15.00, 9.50, 52.00, 0, 20.00, 9.80, 6, 0, 0, 0, 0],
            ['go', 'bombeiro', 'CCT Bombeiro Civil — GO', 'SINDESV/GO', 2700.00, 220, 15.5, 30, 0, 1.5, 220.00, 30.00, 20.00, 20.00, 16.00, 90.00, 42.00, 28.00, 9.80, 6, 0, 0, 0, 0],
            ['mg', 'vigilancia', 'CCT Vigilância — MG', 'SINDESV/MG × SIEMACO-MG', 2200.00, 220, 15.5, 30, 20, 1.5, 210.00, 29.00, 16.00, 19.00, 13.00, 82.00, 28.00, 27.00, 10.00, 6, 44.00, 48.00, 118.00, 34.00],
            ['mg', 'portaria', 'CCT Portaria e Recepção — MG', 'FETHE/MG', 1720.00, 220, 15.5, 0, 0, 1.5, 165.00, 25.00, 13.00, 16.00, 11.00, 58.00, 0, 22.00, 10.00, 6, 0, 0, 0, 0],
            ['mg', 'limpeza', 'CCT Limpeza e Conservação — MG', 'FENASERHTT/MG', 1412.00, 220, 22, 0, 0, 1.0, 145.00, 22.00, 13.00, 15.00, 9.00, 50.00, 0, 21.00, 10.00, 6, 0, 0, 0, 0],
            ['mg', 'bombeiro', 'CCT Bombeiro Civil — MG', 'SINDESV/MG', 2650.00, 220, 15.5, 30, 0, 1.5, 210.00, 29.00, 19.00, 19.00, 15.00, 88.00, 40.00, 27.00, 10.00, 6, 0, 0, 0, 0],
            ['mt', 'vigilancia', 'CCT Vigilância — MT', 'SINDESV/MT × SIEMACO-MT', 2180.00, 220, 15.5, 30, 20, 1.5, 205.00, 28.00, 15.00, 18.00, 12.50, 80.00, 27.00, 26.00, 9.60, 6, 43.00, 47.00, 115.00, 33.00],
            ['mt', 'portaria', 'CCT Portaria e Recepção — MT', 'FETHE/MT', 1700.00, 220, 15.5, 0, 0, 1.5, 162.00, 24.00, 13.00, 16.00, 10.50, 57.00, 0, 21.00, 9.60, 6, 0, 0, 0, 0],
            ['mt', 'limpeza', 'CCT Limpeza e Conservação — MT', 'FENASERHTT/MT', 1400.00, 220, 22, 0, 0, 1.0, 142.00, 21.00, 12.00, 14.00, 8.80, 48.00, 0, 20.00, 9.60, 6, 0, 0, 0, 0],
            ['mt', 'bombeiro', 'CCT Bombeiro Civil — MT', 'SINDESV/MT', 2600.00, 220, 15.5, 30, 0, 1.5, 205.00, 28.00, 18.00, 18.00, 14.50, 86.00, 38.00, 26.00, 9.60, 6, 0, 0, 0, 0],
            ['sp', 'vigilancia', 'CCT Vigilância — SP', 'SINDESV/SP × SIEMACO-SP', 2480.00, 220, 15.5, 30, 20, 1.5, 260.00, 34.00, 20.00, 24.00, 15.50, 92.00, 35.00, 32.00, 11.20, 6, 50.00, 58.00, 130.00, 40.00],
            ['sp', 'portaria', 'CCT Portaria e Recepção — SP', 'FETHE/SP', 1980.00, 220, 15.5, 0, 0, 1.5, 195.00, 30.00, 16.00, 20.00, 13.50, 70.00, 0, 27.00, 11.20, 6, 0, 0, 0, 0],
            ['sp', 'limpeza', 'CCT Limpeza e Conservação — SP', 'FENASERHTT/SP', 1620.00, 220, 22, 0, 0, 1.0, 172.00, 27.00, 15.00, 17.00, 11.00, 60.00, 0, 24.00, 11.20, 6, 0, 0, 0, 0],
            ['sp', 'bombeiro', 'CCT Bombeiro Civil — SP', 'SINDESV/SP', 2950.00, 220, 15.5, 30, 0, 1.5, 260.00, 34.00, 24.00, 24.00, 20.00, 100.00, 48.00, 32.00, 11.20, 6, 0, 0, 0, 0],
        ];

        $tipoMeta = [
            'vigilancia' => ['tipo' => 'seg', 'icone' => '🛡️'],
            'bombeiro' => ['tipo' => 'seg', 'icone' => '🔥'],
            'portaria' => ['tipo' => 'apoio', 'icone' => '🏢'],
            'limpeza' => ['tipo' => 'apoio', 'icone' => '🧹'],
        ];

        foreach ($rows as $r) {
            Cct::updateOrCreate(
                ['uf' => $r[0], 'servico' => $r[1]],
                [
                    'nome' => $r[2], 'titulo' => $r[2], 'sindicato' => $r[3], 'ano_base' => '2026', 'ativo' => true,
                    'tipo' => $tipoMeta[$r[1]]['tipo'] ?? 'apoio',
                    'icone' => $tipoMeta[$r[1]]['icone'] ?? '⭐',
                    'salario_base' => $r[4], 'horas_mes' => $r[5], 'dias_mes' => $r[6],
                    'periculosidade_pct' => $r[7], 'adicional_noturno_pct' => $r[8], 'intrajornada_h' => $r[9],
                    'plano_saude' => $r[10], 'fundo_social' => $r[11], 'sst' => $r[12], 'cna' => $r[13], 'seguro_vida' => $r[14],
                    'uniforme' => $r[15], 'reciclagem' => $r[16], 'va' => $r[17], 'vt' => $r[18], 'desconto_vt_pct' => $r[19],
                    'gta' => $r[20], 'cofre' => $r[21], 'arma' => $r[22], 'colete' => $r[23],
                ],
            );
        }
    }
}
