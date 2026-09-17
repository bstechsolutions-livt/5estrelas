<?php

namespace Tests\Unit;

use App\Support\CnpjNormalizer;
use PHPUnit\Framework\TestCase;

class CnpjNormalizerTest extends TestCase
{
    public function test_mantem_cnpj_14_valido(): void
    {
        $this->assertSame('19593175000188', CnpjNormalizer::normalize('19593175000188'));
        $this->assertSame('19593175000188', CnpjNormalizer::normalize('19.593.175/0001-88'));
    }

    public function test_remove_float_textual_da_senior(): void
    {
        $this->assertSame('19593175000188', CnpjNormalizer::normalize('19593175000188.0'));
    }

    public function test_corta_zero_extra_de_15_digitos(): void
    {
        $this->assertSame('14995581000153', CnpjNormalizer::normalize('149955810001530'));
    }

    public function test_completa_zeros_a_esquerda_12_e_13_digitos(): void
    {
        $this->assertSame('00360305000104', CnpjNormalizer::normalize('360305000104'));
        $this->assertSame('02830621000128', CnpjNormalizer::normalize('2830621000128'));
    }

    public function test_nao_promove_nove_digitos_a_cnpj_nem_a_cpf(): void
    {
        $this->assertSame('150995121', CnpjNormalizer::normalize('150995121'));
        $this->assertFalse(CnpjNormalizer::isCnpj(CnpjNormalizer::normalize('150995121')));
        $this->assertFalse(CnpjNormalizer::isCpf(CnpjNormalizer::normalize('150995121')));
    }

    public function test_zero_e_vazio_viram_nulo(): void
    {
        $this->assertNull(CnpjNormalizer::normalize(null));
        $this->assertNull(CnpjNormalizer::normalize(''));
        $this->assertNull(CnpjNormalizer::normalize('0'));
        $this->assertNull(CnpjNormalizer::normalize('000'));
    }

    public function test_cnpj14_so_aceita_cnpj_valido(): void
    {
        $this->assertSame('19593175000188', CnpjNormalizer::cnpj14('19.593.175/0001-88'));
        $this->assertNull(CnpjNormalizer::cnpj14('12.345.678/0001-99'));
        $this->assertNull(CnpjNormalizer::cnpj14('52998224725'));
    }
}
