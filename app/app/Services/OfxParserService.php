<?php

namespace App\Services;

use App\Services\Ofx\OfxMeta;
use App\Services\Ofx\OfxParseException;
use App\Services\Ofx\OfxParseResult;
use App\Services\Ofx\OfxTransaction;
use Carbon\Carbon;

class OfxParserService
{
    /**
     * Parse completo de um arquivo OFX.
     *
     * @throws OfxParseException se arquivo inválido
     */
    public function parse(string $content): OfxParseResult
    {
        // Validate it's an OFX file
        if (stripos($content, 'OFXHEADER') === false && stripos($content, '<OFX>') === false) {
            throw new OfxParseException('Arquivo não é um OFX válido: header OFXHEADER não encontrado.');
        }

        if (stripos($content, '<OFX>') === false && stripos($content, '<OFX') === false) {
            throw new OfxParseException('Arquivo OFX inválido: bloco <OFX> não encontrado.');
        }

        $meta = $this->parseAccountInfo($content);

        // Extract ORG name from SIGNONMSGSRSV1
        $meta->orgName = $this->extractOrgName($content);

        // Extract period from BANKTRANLIST
        $this->parsePeriod($content, $meta);

        // Extract balance
        $balance = $this->parseBalance($content);
        if ($balance !== null) {
            $meta->balance = $balance['amount'];
            $meta->balanceDate = $balance['date'];
        }

        // Extract transactions
        $transactions = $this->parseTransactions($content);

        return new OfxParseResult(
            meta: $meta,
            transactions: $transactions,
        );
    }

    /**
     * Extract ORG name from the SIGNONMSGSRSV1 / FI block.
     */
    private function extractOrgName(string $content): ?string
    {
        // Try to find the FI block
        $fiStart = stripos($content, '<FI>');
        if ($fiStart === false) {
            return null;
        }

        // Look for end of FI block or just search within a reasonable range
        $fiEnd = stripos($content, '</FI>', $fiStart);
        if ($fiEnd === false) {
            // SGML style — take next ~200 chars
            $fiBlock = substr($content, $fiStart, 200);
        } else {
            $fiBlock = substr($content, $fiStart, $fiEnd - $fiStart);
        }

        return $this->extractTagValue($fiBlock, 'ORG');
    }

    /**
     * Extract value of an OFX tag.
     * Supports both XML-like (<TAG>value</TAG>) and SGML (<TAG>value\n).
     */
    public function extractTagValue(string $content, string $tag): ?string
    {
        // Try XML-like first: <TAG>value</TAG>
        $pattern = '/<' . preg_quote($tag, '/') . '>(.*?)<\/' . preg_quote($tag, '/') . '>/si';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }

        // Fallback to SGML: <TAG>value until next < or end of line
        $pattern = '/<' . preg_quote($tag, '/') . '>([^<\r\n]+)/i';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Convert TRNAMT to float.
     * Handles: dot decimal, comma decimal (Santander), explicit + sign (BRB).
     */
    public function parseAmount(string $raw): float
    {
        // Remove whitespace
        $raw = trim($raw);

        // If contains comma and NO dot → comma is decimal separator (Santander: 17964,26)
        if (str_contains($raw, ',') && !str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',') && str_contains($raw, '.')) {
            // Both present: comma is thousands separator, dot is decimal (standard EN)
            $raw = str_replace(',', '', $raw);
        }

        // If starts with + → remove it (BRB: +19992.60)
        if (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }

        return (float) $raw;
    }

    /**
     * Convert DTPOSTED to Carbon date (ignores time/timezone).
     * Takes first 8 chars: YYYYMMDD.
     */
    public function parseDate(string $raw): Carbon
    {
        $date8 = substr(trim($raw), 0, 8);

        return Carbon::createFromFormat('Ymd', $date8)->startOfDay();
    }

    /**
     * Extract BANKACCTFROM block → account metadata.
     */
    private function parseAccountInfo(string $content): OfxMeta
    {
        $meta = new OfxMeta();

        // Find BANKACCTFROM block
        $acctStart = stripos($content, '<BANKACCTFROM>');
        if ($acctStart === false) {
            return $meta;
        }

        // Find end of block
        $acctEnd = stripos($content, '</BANKACCTFROM>', $acctStart);
        if ($acctEnd === false) {
            // SGML — take a reasonable chunk
            $acctBlock = substr($content, $acctStart, 500);
        } else {
            $acctBlock = substr($content, $acctStart, $acctEnd - $acctStart);
        }

        $meta->bankId = $this->extractTagValue($acctBlock, 'BANKID');
        $meta->accountId = $this->extractTagValue($acctBlock, 'ACCTID');
        $meta->branchId = $this->extractTagValue($acctBlock, 'BRANCHID');
        $meta->accountType = $this->extractTagValue($acctBlock, 'ACCTTYPE');

        return $meta;
    }

    /**
     * Extract period (DTSTART/DTEND) from BANKTRANLIST.
     */
    private function parsePeriod(string $content, OfxMeta $meta): void
    {
        $listStart = stripos($content, '<BANKTRANLIST>');
        if ($listStart === false) {
            return;
        }

        $listBlock = substr($content, $listStart, 500);

        $dtStart = $this->extractTagValue($listBlock, 'DTSTART');
        if ($dtStart) {
            $meta->periodStart = $this->parseDate($dtStart);
        }

        $dtEnd = $this->extractTagValue($listBlock, 'DTEND');
        if ($dtEnd) {
            $meta->periodEnd = $this->parseDate($dtEnd);
        }
    }

    /**
     * Extract all transactions from BANKTRANLIST.
     * Filters out transactions with amount == 0.00.
     */
    private function parseTransactions(string $content): array
    {
        $transactions = [];

        // Find all STMTTRN blocks
        // Use regex to split on <STMTTRN> markers
        $pattern = '/<STMTTRN>(.*?)(?:<\/STMTTRN>|(?=<STMTTRN>)|(?=<\/BANKTRANLIST>))/si';

        if (!preg_match_all($pattern, $content, $matches)) {
            return [];
        }

        foreach ($matches[1] as $block) {
            $type = $this->extractTagValue($block, 'TRNTYPE');
            $dtPosted = $this->extractTagValue($block, 'DTPOSTED');
            $trnAmt = $this->extractTagValue($block, 'TRNAMT');

            if ($type === null || $dtPosted === null || $trnAmt === null) {
                continue;
            }

            $amount = $this->parseAmount($trnAmt);

            // Skip zero-amount transactions (saldo lines)
            if (abs($amount) < 0.001) {
                continue;
            }

            $fitid = $this->extractTagValue($block, 'FITID');
            $name = $this->extractTagValue($block, 'NAME');
            $memo = $this->extractTagValue($block, 'MEMO');
            $checkNum = $this->extractTagValue($block, 'CHECKNUM');

            // Build raw data array
            $rawData = [
                'TRNTYPE' => $type,
                'DTPOSTED' => $dtPosted,
                'TRNAMT' => $trnAmt,
                'FITID' => $fitid,
                'NAME' => $name,
                'MEMO' => $memo,
                'CHECKNUM' => $checkNum,
            ];

            $transactions[] = new OfxTransaction(
                type: strtoupper($type),
                date: $this->parseDate($dtPosted),
                amount: $amount,
                fitid: $fitid ?: null,
                name: $name,
                memo: $memo,
                checkNum: $checkNum,
                rawData: array_filter($rawData, fn($v) => $v !== null),
            );
        }

        return $transactions;
    }

    /**
     * Extract LEDGERBAL (balance amount and date).
     */
    private function parseBalance(string $content): ?array
    {
        $balStart = stripos($content, '<LEDGERBAL>');
        if ($balStart === false) {
            return null;
        }

        $balEnd = stripos($content, '</LEDGERBAL>', $balStart);
        if ($balEnd === false) {
            // SGML — take a reasonable chunk after LEDGERBAL
            $balBlock = substr($content, $balStart, 300);
        } else {
            $balBlock = substr($content, $balStart, $balEnd - $balStart);
        }

        $balAmt = $this->extractTagValue($balBlock, 'BALAMT');
        $dtAsOf = $this->extractTagValue($balBlock, 'DTASOF');

        if ($balAmt === null) {
            return null;
        }

        return [
            'amount' => $this->parseAmount($balAmt),
            'date' => $dtAsOf ? $this->parseDate($dtAsOf) : null,
        ];
    }
}
