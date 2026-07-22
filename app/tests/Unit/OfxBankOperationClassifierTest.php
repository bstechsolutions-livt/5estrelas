<?php

namespace Tests\Unit;

use App\Models\BankDayOperation;
use App\Services\OfxBankOperationClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfxBankOperationClassifierTest extends TestCase
{
    private OfxBankOperationClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new OfxBankOperationClassifier;
    }

    #[DataProvider('tarifaProvider')]
    public function test_classifies_tarifas(string $description): void
    {
        $this->assertSame(
            BankDayOperation::CATEGORY_TARIFA,
            $this->classifier->classify($description)
        );
    }

    public static function tarifaProvider(): array
    {
        return [
            ['TARIFA AVULSA ENVIO PIX'],
            ['TARIFA BAIXA OU DEVOL DE TITULO'],
            ['TAR LIQ COB COM REG COMPE'],
            ['TARIFA MANUTENCAO TIT VENCIDO'],
        ];
    }

    public function test_classifies_aplicacao(): void
    {
        $this->assertSame(
            BankDayOperation::CATEGORY_APLICACAO,
            $this->classifier->classify('APLICACAO CONTAMAX')
        );
    }

    public function test_classifies_resgate(): void
    {
        $this->assertSame(
            BankDayOperation::CATEGORY_RESGATE,
            $this->classifier->classify('RESGATE FUNDO XYZ')
        );
    }

    public function test_ignores_regular_debits(): void
    {
        $this->assertNull($this->classifier->classify('DEBITO PIX'));
        $this->assertNull($this->classifier->classify('DEBITO RESCISAO'));
        $this->assertNull($this->classifier->classify('DEBITO PAGAMENTO DE SALARIO'));
    }
}
