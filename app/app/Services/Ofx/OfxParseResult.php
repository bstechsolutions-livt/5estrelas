<?php

namespace App\Services\Ofx;

class OfxParseResult
{
    public function __construct(
        public OfxMeta $meta,
        public array $transactions = [], // OfxTransaction[]
    ) {}
}
