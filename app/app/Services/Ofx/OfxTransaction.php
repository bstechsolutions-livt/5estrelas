<?php

namespace App\Services\Ofx;

use Carbon\Carbon;

class OfxTransaction
{
    public function __construct(
        public string $type,       // CREDIT, DEBIT, OTHER
        public Carbon $date,
        public float $amount,      // always with sign: - debit, + credit
        public ?string $fitid = null,
        public ?string $name = null,
        public ?string $memo = null,
        public ?string $checkNum = null,
        public array $rawData = [],
    ) {}
}
