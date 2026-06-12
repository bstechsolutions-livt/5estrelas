<?php

namespace App\Services\Comercial;

/**
 * Motor de cálculo de custos de mão de obra terceirizada.
 * Portado 1:1 do protótipo "Gestão 360º" (ver .kiro/steering/comercial-calculo-in05.md).
 *
 * Dois modelos:
 *  - calcularIN05(): planilha oficial IN 05 (Módulos 1 a 6), valor do posto = precoEmp (gross-up).
 *  - calcular5Estrelas(): calculadora rápida diurno/noturno (encargos como % único).
 *
 * Regras de arredondamento (idênticas ao JS):
 *  - percentuais/frações: 4 casas (Math.round(x*10000)/10000)
 *  - valores R$: 2 casas (Math.round(x*100)/100)
 *  - somas de totais de módulo: sem arredondamento (float)
 */
class ComposicaoCustoService
{
    /** Constantes normativas (default do protótipo; podem vir parametrizadas). */
    public const C_13_AVOS = 0.0833;      // 13º (1/12)
    public const C_MULTA_FGTS_FERIAS = 0.032;  // 3,2% multa FGTS sobre férias
    public const C_MULTA_FGTS_RESC = 0.40;     // 40% multa FGTS rescisão
    public const C_DESC_VT = 0.06;        // 6% desconto VT sobre salário
    public const C_HORAS_AN = 8;          // fator horas adicional noturno

    private function r2(float $v): float
    {
        return round($v, 2);
    }

    private function r4(float $v): float
    {
        return round($v, 4);
    }

    private function num($v, float $default = 0.0): float
    {
        return is_numeric($v) ? (float) $v : $default;
    }

    /**
     * Planilha IN 05 — custo por empregado.
     *
     * @param array $in chaves esperadas (todas opcionais, default conforme protótipo):
     *   sal, dias_mes, horas_mes,
     *   peric_pct, insal_pct, an_pct, hnr_pct, outros1_pct,
     *   inss_pct, saledu_pct, sat_pct, sesc_pct, senai_pct, sebrae_pct, incra_pct, fgts_pct,
     *   vt_dia, va_dia, medico, odonto, cesta, seguro, pmq, outros23,
     *   avisoind_pct, avistrab_pct, ausleg_pct, paterni_pct, acident_pct, matern_pct, intrajornada,
     *   uniforme, materiais, ferramental, epi, treinamento, sso,
     *   custoind_pct, lucro_pct, iss_pct, pis_pct, cofins_pct
     *   (constantes opcionais: c_13, c_multa_ferias, c_multa_resc, c_desc_vt)
     * @return array detalhamento completo + precoEmp
     */
    public function calcularIN05(array $in): array
    {
        $g = fn (string $k, float $d = 0.0) => $this->num($in[$k] ?? null, $d);

        $c13 = $g('c_13', self::C_13_AVOS);
        $cMultaFerias = $g('c_multa_ferias', self::C_MULTA_FGTS_FERIAS);
        $cMultaResc = $g('c_multa_resc', self::C_MULTA_FGTS_RESC);
        $cDescVt = $g('c_desc_vt', self::C_DESC_VT);

        $sal = $g('sal');
        $diasMes = $g('dias_mes', 15.5);
        $horMes = $g('horas_mes', 220);

        // ─── MÓDULO 1 — Composição da Remuneração ───
        $peric = $sal * ($g('peric_pct') / 100);
        $insal = $sal * ($g('insal_pct') / 100);
        $anVal = $horMes > 0 ? $this->r2(($sal + $peric) / $horMes * ($g('an_pct') / 100) * self::C_HORAS_AN * $diasMes) : 0.0;
        $hnrVal = $sal * ($g('hnr_pct') / 100);
        $out1 = $sal * ($g('outros1_pct') / 100);
        $m1 = $sal + $peric + $insal + $anVal + $hnrVal + $out1;

        // ─── MÓDULO 2.2 — GPS/FGTS (base m1) ───
        $inss = $this->r4($m1 * ($g('inss_pct') / 100));
        $saledu = $this->r4($m1 * ($g('saledu_pct') / 100));
        $sat = $this->r4($m1 * ($g('sat_pct') / 100));
        $sesc = $this->r4($m1 * ($g('sesc_pct') / 100));
        $senai = $this->r4($m1 * ($g('senai_pct') / 100));
        $sebrae = $this->r4($m1 * ($g('sebrae_pct') / 100));
        $incra = $this->r4($m1 * ($g('incra_pct') / 100));
        $fgts = $this->r4($m1 * ($g('fgts_pct') / 100));
        $sub22 = $inss + $saledu + $sat + $sesc + $senai + $sebrae + $incra + $fgts;
        $pct22 = $m1 > 0 ? $sub22 / $m1 : 0.0;

        // ─── MÓDULO 2.1 — 13º e Férias (base m1) ───
        $p13 = $c13;
        $pFer = $this->r4($p13 / 3);
        $pInc21 = $this->r4(($p13 + $pFer) * $pct22);
        $pMultaFgts = $this->r4($pFer * $cMultaFerias);
        $v13 = $m1 * $p13;
        $vFer = $m1 * $pFer;
        $vInc21 = $m1 * $pInc21;
        $vMultaFgts = $m1 * $pMultaFgts;
        $sub21 = $v13 + $vFer + $vInc21 + $vMultaFgts;

        // ─── MÓDULO 2.3 — Benefícios ───
        $vtBruto = $g('vt_dia') * $diasMes;
        $vtDesc = $sal * $cDescVt;
        $vtLiq = $this->r2(max($vtBruto - $vtDesc, 0));
        $vaVal = $this->r2($g('va_dia') * $diasMes);
        $medico = $g('medico');
        $odonto = $g('odonto');
        $cesta = $g('cesta');
        $seguro = $g('seguro');
        $pmq = $g('pmq');
        $out23 = $g('outros23');
        $sub23 = $vtLiq + $vaVal + $medico + $odonto + $cesta + $seguro + $pmq + $out23;

        $m2 = $sub21 + $sub22 + $sub23;

        // ─── MÓDULO 3 — Provisão para Rescisão (base m1) ───
        $pAvisoInd = $g('avisoind_pct') / 100;
        $pFgtsPct = $g('fgts_pct') / 100;
        $pFgtsAviso = $this->r4($pAvisoInd * $pFgtsPct);
        $pAvisTrab = $g('avistrab_pct') / 100;
        $pMultaInd = $this->r4($pAvisoInd * $cMultaResc);
        $pMultaResc = $this->r4($pFgtsPct * $cMultaResc);
        $pIncGPS = $this->r4($pAvisTrab * $pct22);
        $vAvisoInd = $this->r2($m1 * $pAvisoInd);
        $vFgtsAviso = $this->r2($m1 * $pFgtsAviso);
        $vAvisTrab = $this->r2($m1 * $pAvisTrab);
        $vMultaInd = $this->r2($m1 * $pMultaInd);
        $vMultaResc = $this->r2($m1 * $pMultaResc);
        $vIncGPS = $this->r2($m1 * $pIncGPS);
        $m3 = $vAvisoInd + $vFgtsAviso + $vAvisTrab + $vMultaInd + $vMultaResc + $vIncGPS;

        // ─── MÓDULO 4 — Reposição do Profissional Ausente (base m1) ───
        $pCobFer = $this->r4(($p13 + $pFer) / 12 + $p13);
        $pAusleg = $g('ausleg_pct') / 100;
        $pPatern = $g('paterni_pct') / 100;
        $pAcident = $g('acident_pct') / 100;
        $pMatern = $g('matern_pct') / 100;
        $subtot41pct = $pCobFer + $pAusleg + $pPatern + $pAcident + $pMatern;
        $pct21total = ($p13 + $pFer) * $pct22 + ($p13 + $pFer);
        $pIncAus = $this->r4($subtot41pct * ($pct22 + $pct21total));
        $vCobFer = $this->r2($m1 * $pCobFer);
        $vAusleg = $this->r2($m1 * $pAusleg);
        $vPatern = $this->r2($m1 * $pPatern);
        $vAcident = $this->r2($m1 * $pAcident);
        $vMatern = $this->r2($m1 * $pMatern);
        $sub41 = $vCobFer + $vAusleg + $vPatern + $vAcident + $vMatern;
        $vIncAus = $this->r2($m1 * $pIncAus);
        $tot41 = $sub41 + $vIncAus;
        $m4intra = $g('intrajornada');
        $m4 = $tot41 + $m4intra;

        // ─── MÓDULO 5 — Insumos ───
        $m5 = $g('uniforme') + $g('materiais') + $g('ferramental') + $g('epi') + $g('treinamento') + $g('sso');

        // ─── MÓDULO 6 — Custos Indiretos, Tributos e Lucro ───
        $subtotal = $m1 + $m2 + $m3 + $m4 + $m5;
        $pCind = $g('custoind_pct') / 100;
        $pLucro = $g('lucro_pct') / 100;
        $pISS = $g('iss_pct') / 100;
        $pPIS = $g('pis_pct') / 100;
        $pCOFINS = $g('cofins_pct') / 100;
        $pTrib = $pISS + $pPIS + $pCOFINS;
        $d140 = (1 + $pCind) * (1 + $pLucro) / (1 - $pTrib) - 1;
        $precoEmp = $this->r2($subtotal * (1 + $d140));
        $vCind = $this->r2($subtotal * $pCind);
        $vLucro = $this->r2(($subtotal + $vCind) * $pLucro);
        $vISS = $this->r2($precoEmp * $pISS);
        $vPIS = $this->r2($precoEmp * $pPIS);
        $vCOFINS = $this->r2($precoEmp * $pCOFINS);
        $m6 = $vCind + $vLucro + $vISS + $vPIS + $vCOFINS;

        return [
            'modulo1' => [
                'peric' => $peric, 'insal' => $insal, 'adicional_noturno' => $anVal,
                'hnr' => $hnrVal, 'outros' => $out1, 'total' => $m1,
            ],
            'modulo2' => [
                'sub21' => $sub21, 'sub22' => $sub22, 'sub23' => $sub23,
                'pct22' => $pct22, 'vt_liquido' => $vtLiq, 'va' => $vaVal, 'total' => $m2,
            ],
            'modulo3' => ['total' => $m3],
            'modulo4' => ['sub41' => $sub41, 'incidencia' => $vIncAus, 'intrajornada' => $m4intra, 'total' => $m4],
            'modulo5' => ['total' => $m5],
            'modulo6' => [
                'custos_indiretos' => $vCind, 'lucro' => $vLucro,
                'iss' => $vISS, 'pis' => $vPIS, 'cofins' => $vCOFINS, 'total' => $m6,
            ],
            'subtotal' => $subtotal,
            'fator_grossup' => $d140,
            'preco_empregado' => $precoEmp,
        ];
    }

    /**
     * Modelo 5 Estrelas — calculadora rápida diurno/noturno.
     *
     * @param array $in qtd_diurno, sal_diurno, qtd_noturno, sal_noturno,
     *   an_diurno(0/1), an_noturno(0/1), encargos_pct, pct_adm, pct_lucro, pct_impostos,
     *   peric_pct, intra_h, desc_vt_pct, dias_mes, horas_mes,
     *   beneficios: [uniforme,saude,fundo,sst,cna,seguro,gta,cofre,arma,reciclag,vt,va] (unitários),
     *   meses
     * @return array
     */
    public function calcular5Estrelas(array $in): array
    {
        $g = fn (string $k, float $d = 0.0) => $this->num($in[$k] ?? null, $d);
        $b = fn (string $k) => $this->num(($in['beneficios'][$k] ?? null), 0.0);

        $qtdD = $g('qtd_diurno');
        $salD = $g('sal_diurno');
        $qtdN = $g('qtd_noturno');
        $salN = $g('sal_noturno');
        $anD = (int) $g('an_diurno', 0);
        $anN = (int) $g('an_noturno', 1);

        $diasMes = $g('dias_mes', 15.5);
        $horasMes = $g('horas_mes', 220);
        $pericPct = $g('peric_pct') / 100;
        $anPct = 20 / 100;
        $intraH = $g('intra_h', 1.5);

        // Turno Diurno
        $pericD = $salD * $pericPct;
        $adnD = $anD ? ($salD + $pericD) / $horasMes * $anPct * self::C_HORAS_AN * $diasMes : 0;
        $intraD = $horasMes > 0 ? ($salD + $pericD + $adnD) / $horasMes * $intraH * $diasMes : 0;
        $totD = ($salD + $pericD + $adnD + $intraD) * $qtdD;

        // Turno Noturno
        $pericN = $salN * $pericPct;
        $adnN = $anN ? ($salN + $pericN) / $horasMes * $anPct * self::C_HORAS_AN * $diasMes : 0;
        $intraN = $horasMes > 0 ? ($salN + $pericN + $adnN) / $horasMes * $intraH * $diasMes : 0;
        $totN = ($salN + $pericN + $adnN + $intraN) * $qtdN;

        $totalFunc = $qtdD + $qtdN;
        $remTotal = $totD + $totN;
        $encPct = $g('encargos_pct') / 100;
        $encVal = $remTotal * $encPct;
        $m1 = $remTotal + $encVal;

        // M2 — benefícios
        $salBase = $salD ?: $salN;
        $mults = [
            'uniforme' => $totalFunc, 'saude' => $totalFunc, 'fundo' => $totalFunc,
            'sst' => $totalFunc, 'cna' => $totalFunc, 'seguro' => $totalFunc,
            'gta' => 1, 'cofre' => 1, 'arma' => 1, 'reciclag' => $totalFunc,
            'vt' => $diasMes * $totalFunc, 'va' => $diasMes * $totalFunc,
        ];
        $m2 = 0.0;
        foreach ($mults as $id => $mult) {
            $m2 += $b($id) * $mult;
        }
        $descVTPct = $g('desc_vt_pct', 6) / 100;
        $descVT = $descVTPct * $salBase * $totalFunc;
        $m2 -= $descVT;

        // M3 — adm/lucro/impostos
        $base = $m1 + $m2;
        $pAdm = $g('pct_adm') / 100;
        $pLucro = $g('pct_lucro') / 100;
        $pImp = $g('pct_impostos') / 100;
        $vAdm = $base * $pAdm;
        $vLucro = ($base + $vAdm) * $pLucro;
        $total3 = $base + $vAdm + $vLucro;
        $vImp = $total3 * $pImp;
        $grandTotal = $total3 + $vImp;
        $valorPessoa = $totalFunc > 0 ? $grandTotal / $totalFunc : 0;
        $meses = (int) $g('meses', 12);
        $vaTotal = $b('va') * $diasMes * $totalFunc;

        return [
            'total_funcionarios' => $totalFunc,
            'remuneracao' => $remTotal,
            'modulo1' => $m1,
            'modulo2' => $m2,
            'modulo3' => $vAdm + $vLucro + $vImp,
            'adm' => $vAdm, 'lucro' => $vLucro, 'impostos' => $vImp,
            'mensal' => $grandTotal,
            'anual' => $grandTotal * $meses,
            'valor_por_funcionario' => $valorPessoa,
            'va_total' => $vaTotal,
        ];
    }
}
