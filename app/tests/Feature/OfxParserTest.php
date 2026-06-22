<?php

namespace Tests\Feature;

use App\Services\Ofx\OfxParseException;
use App\Services\OfxParserService;
use Tests\TestCase;

class OfxParserTest extends TestCase
{
    private OfxParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new OfxParserService();
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/fixtures/ofx/{$name}"));
    }

    // BB tests
    public function test_parse_bb(): void
    {
        $result = $this->parser->parse($this->fixture('bb.ofx'));

        $this->assertEquals('1', $result->meta->bankId);
        $this->assertEquals('51356', $result->meta->accountId);
        $this->assertEquals('2901', $result->meta->branchId);
        $this->assertEquals('Banco do Brasil', $result->meta->orgName);
        $this->assertCount(4, $result->transactions); // 7 total - 3 with amount 0.00

        // First non-zero transaction: debit -93.10
        $debit = collect($result->transactions)->first(fn ($t) => $t->amount < 0);
        $this->assertEquals(-93.10, $debit->amount);
        $this->assertEquals('DEBIT', $debit->type);
        $this->assertEquals('2026-05-06', $debit->date->format('Y-m-d'));
    }

    // Santander tests (comma decimal)
    public function test_parse_santander(): void
    {
        $result = $this->parser->parse($this->fixture('santander.ofx'));

        $this->assertEquals('033', $result->meta->bankId);
        $this->assertEquals('2269130023875', $result->meta->accountId);
        $this->assertEquals('SANTANDER', $result->meta->orgName);
        $this->assertCount(2, $result->transactions);

        // Credit: 17964.26 (parsed from comma)
        $credit = collect($result->transactions)->first(fn ($t) => $t->amount > 0);
        $this->assertEquals(17964.26, $credit->amount);

        // Debit: -17964.26
        $debit = collect($result->transactions)->first(fn ($t) => $t->amount < 0);
        $this->assertEquals(-17964.26, $debit->amount);
    }

    // BRB tests (explicit + sign)
    public function test_parse_brb(): void
    {
        $result = $this->parser->parse($this->fixture('brb.ofx'));

        $this->assertEquals('070', $result->meta->bankId);
        $this->assertEquals('0460001329', $result->meta->accountId);
        $this->assertEquals('Banco de Brasília', $result->meta->orgName);
        $this->assertCount(2, $result->transactions);

        // Credit with + sign: +19992.60
        $credit = collect($result->transactions)->first(fn ($t) => $t->amount > 0);
        $this->assertEquals(19992.60, $credit->amount);
        $this->assertEquals('2026-06-18', $credit->date->format('Y-m-d'));

        // Debit: -110.90
        $debit = collect($result->transactions)->first(fn ($t) => $t->amount < 0);
        $this->assertEquals(-110.90, $debit->amount);
    }

    // Banrisul tests (empty transaction list)
    public function test_parse_banrisul_empty(): void
    {
        $result = $this->parser->parse($this->fixture('banrisul.ofx'));

        $this->assertEquals('041', $result->meta->bankId);
        $this->assertEquals('01350685083605', $result->meta->accountId);
        $this->assertCount(0, $result->transactions);
        $this->assertEquals(0.00, $result->meta->balance);
    }

    // Invalid file
    public function test_parse_invalid_file(): void
    {
        $this->expectException(OfxParseException::class);
        $this->parser->parse('this is not an OFX file');
    }

    // Balance extraction
    public function test_balance_extraction(): void
    {
        $result = $this->parser->parse($this->fixture('brb.ofx'));
        $this->assertEquals(110544.86, $result->meta->balance);
    }

    // Date parsing with timezone
    public function test_date_parsing_ignores_timezone(): void
    {
        $result = $this->parser->parse($this->fixture('brb.ofx'));
        $tx = $result->transactions[0];
        $this->assertEquals('2026-06-18', $tx->date->format('Y-m-d'));
    }

    // Amount zero filtered
    public function test_zero_amount_transactions_filtered(): void
    {
        $result = $this->parser->parse($this->fixture('bb.ofx'));
        foreach ($result->transactions as $tx) {
            $this->assertNotEquals(0.00, $tx->amount);
        }
    }
}
