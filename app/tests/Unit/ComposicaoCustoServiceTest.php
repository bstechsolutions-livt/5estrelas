<?php

namespace Tests\Unit;

use App\Services\Comercial\ComposicaoCustoService;
use PHPUnit\Framework\TestCase;

/**
 * Verifica que o motor de cálculo PHP bate NÚMERO-A-NÚMERO com a planilha
 * do protótipo "Gestão 360º" (calcIN / calcular).
 *
 * Os valores "golden" foram extraídos rodando a função JS ORIGINAL do protótipo
 * (calcIN) com mock de DOM e os inputs default, via harness Node.
 */
class ComposicaoCustoServiceTest extends TestCase
{
    private ComposicaoCustoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ComposicaoCustoService();
    }

    /** Inputs default do protótipo (atributos value="" dos inputs in-*). */
    private function inputsDefaultIN05(): array
    {
        return [
            'sal' => 1850, 'dias_mes' => 15.5, 'horas_mes' => 220,
            'peric_pct' => 0, 'insal_pct' => 0, 'an_pct' => 0, 'hnr_pct' => 0, 'outros1_pct' => 0,
            'inss_pct' => 20, 'saledu_pct' => 2.5, 'sat_pct' => 3.28, 'sesc_pct' => 1.5,
            'senai_pct' => 1, 'sebrae_pct' => 0.6, 'incra_pct' => 0.2, 'fgts_pct' => 8,
            'vt_dia' => 10.4, 'va_dia' => 30, 'medico' => 0, 'odonto' => 0, 'cesta' => 0,
            'seguro' => 14.2, 'pmq' => 0, 'outros23' => 0,
            'avisoind_pct' => 1, 'avistrab_pct' => 0.59, 'ausleg_pct' => 0.1,
            'paterni_pct' => 0.02, 'acident_pct' => 0.1, 'matern_pct' => 0.02, 'intrajornada' => 0,
            'uniforme' => 89.5, 'materiais' => 0, 'ferramental' => 0, 'epi' => 0, 'treinamento' => 0, 'sso' => 18,
            'custoind_pct' => 5, 'lucro_pct' => 3, 'iss_pct' => 5, 'pis_pct' => 1.65, 'cofins_pct' => 7.6,
        ];
    }

    public function test_in05_bate_com_o_prototipo_nos_defaults(): void
    {
        $r = $this->svc->calcularIN05($this->inputsDefaultIN05());

        // Golden do protótipo (calcIN original, escala 24h)
        $this->assertEqualsWithDelta(1850.00, $r['modulo1']['total'], 0.005, 'Módulo 1');
        $this->assertEqualsWithDelta(1498.80, $r['modulo2']['total'], 0.005, 'Módulo 2');
        $this->assertEqualsWithDelta(101.57, $r['modulo3']['total'], 0.005, 'Módulo 3');
        $this->assertEqualsWithDelta(267.70, $r['modulo4']['total'], 0.005, 'Módulo 4');
        $this->assertEqualsWithDelta(107.50, $r['modulo5']['total'], 0.005, 'Módulo 5');
        $this->assertEqualsWithDelta(999.34, $r['modulo6']['total'], 0.005, 'Módulo 6');
        $this->assertEqualsWithDelta(3825.57, $r['subtotal'], 0.005, 'Subtotal');
        $this->assertEqualsWithDelta(4824.90, $r['preco_empregado'], 0.005, 'Preço por empregado');
    }

    public function test_in05_anual_eh_preco_vezes_meses(): void
    {
        $r = $this->svc->calcularIN05($this->inputsDefaultIN05());
        $this->assertEqualsWithDelta(57898.80, $r['preco_empregado'] * 12, 0.01, 'Valor anual');
    }

    public function test_in05_com_periculosidade_30_recalcula_m1(): void
    {
        $in = $this->inputsDefaultIN05();
        $in['peric_pct'] = 30;
        $r = $this->svc->calcularIN05($in);
        // M1 = sal + peric(30% de 1850 = 555) = 2405
        $this->assertEqualsWithDelta(2405.00, $r['modulo1']['total'], 0.005);
    }

    public function test_in05_gross_up_tributos_aumenta_preco(): void
    {
        $r = $this->svc->calcularIN05($this->inputsDefaultIN05());
        // precoEmp deve ser > subtotal (gross-up adm/lucro/tributos)
        $this->assertGreaterThan($r['subtotal'], $r['preco_empregado']);
    }

    public function test_in05_vale_transporte_nunca_negativo(): void
    {
        $in = $this->inputsDefaultIN05();
        $in['vt_dia'] = 1; // VT bruto baixo, desconto 6% do salário seria maior → líquido não pode ser negativo
        $r = $this->svc->calcularIN05($in);
        $this->assertGreaterThanOrEqual(0, $r['modulo2']['vt_liquido']);
    }

    public function test_modelo_5estrelas_bate_com_o_prototipo(): void
    {
        // Cenário determinístico verificado contra calcular()/M2/M3 do protótipo
        $r = $this->svc->calcular5Estrelas([
            'qtd_diurno' => 2, 'sal_diurno' => 2000, 'qtd_noturno' => 0, 'sal_noturno' => 0,
            'an_diurno' => 0, 'an_noturno' => 1,
            'encargos_pct' => 72.11, 'pct_adm' => 5, 'pct_lucro' => 3, 'pct_impostos' => 8.65,
            'peric_pct' => 0, 'intra_h' => 1.5, 'desc_vt_pct' => 6, 'dias_mes' => 15.5, 'horas_mes' => 220,
            'beneficios' => [], 'meses' => 12,
        ]);

        $this->assertEqualsWithDelta(7611.96, $r['modulo1'], 0.01, 'M1 (5E)');
        $this->assertEqualsWithDelta(-240.00, $r['modulo2'], 0.01, 'M2 (5E)');
        $this->assertEqualsWithDelta(1290.46, $r['modulo3'], 0.01, 'M3 (5E)');
        $this->assertEqualsWithDelta(8662.41, $r['mensal'], 0.01, 'Mensal (5E)');
        $this->assertEqualsWithDelta(103948.98, $r['anual'], 0.05, 'Anual (5E)');
    }

    public function test_5estrelas_sem_funcionarios_nao_quebra(): void
    {
        $r = $this->svc->calcular5Estrelas(['qtd_diurno' => 0, 'qtd_noturno' => 0]);
        $this->assertEquals(0, $r['valor_por_funcionario']);
        $this->assertEquals(0, $r['total_funcionarios']);
    }
}
