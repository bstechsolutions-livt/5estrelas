<?php

namespace Tests\Unit\Services\Senior;

use App\Services\Senior\SupplierDisplayNameResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierDisplayNameResolverTest extends TestCase
{
    private SupplierDisplayNameResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SupplierDisplayNameResolver();
    }

    #[Test]
    public function extrai_nome_de_gfd_e_trct(): void
    {
        $this->assertSame('GILSON DE SOUZA', $this->resolver->fromDescription('GFD - GILSON DE SOUZA'));
        $this->assertSame('GABRIEL MARTINS DA SILVA', $this->resolver->fromDescription('REF. A TRCT GABRIEL MARTINS DA SILVA'));
        $this->assertSame('SUSAYNA TAYNA SOUSA FUKUTA', $this->resolver->fromDescription('REF. A GFD SUSAYNA TAYNA SOUSA FUKUTA'));
    }

    #[Test]
    public function usa_primeira_linha_da_descricao_como_fallback(): void
    {
        $this->assertSame(
            'MANUTENÇÕES REALIZADAS, TROCA DE OLEO',
            $this->resolver->fromDescription("MANUTENÇÕES REALIZADAS, TROCA DE OLEO\nsegunda linha"),
        );
    }

    #[Test]
    public function ignora_observacao_longa_de_titulo_manual(): void
    {
        $this->assertNull($this->resolver->fromDescription(
            'REFERENTE A INFRAÇÃO DE TRANSITO KK01995872 - PLACA SGT7F81- RESIDÊNCIA QL 10',
        ));
    }

    #[Test]
    public function identifica_nome_generico(): void
    {
        $this->assertTrue($this->resolver->isGeneric('Fornecedor 2633'));
        $this->assertFalse($this->resolver->isGeneric('GILSON DE SOUZA'));
    }
}
