<?php

namespace App\Services\Ofx;

use Carbon\Carbon;

class OfxMeta
{
    public function __construct(
        public ?string $bankId = null,
        public ?string $accountId = null,
        public ?string $branchId = null,
        public ?string $accountType = null,
        public ?string $orgName = null,
        public ?Carbon $periodStart = null,
        public ?Carbon $periodEnd = null,
        public ?float $balance = null,
        public ?Carbon $balanceDate = null,
    ) {}
}
