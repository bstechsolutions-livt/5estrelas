<?php

namespace App\Support;

class OfficialBankAccountCatalog
{
    /**
     * Relação oficial fornecida pelo Financeiro em 17/07/2026.
     *
     * @return list<array{
     *   unit: string,
     *   bank_name: string,
     *   bank_code: string,
     *   agency: string,
     *   account_number: string,
     *   account_digit: string,
     *   senior_codemp: int,
     *   senior_num_cco: string
     * }>
     */
    public static function all(): array
    {
        return [
            self::account('MATRIZ', 'BRB', '070', '046', '000134', '5', 2, '18'),
            self::account('MATRIZ', 'BRB', '070', '050', '039912', '3', 2, '103'),
            self::account('MATRIZ', 'BRB', '070', '060', '059124', '7', 2, '112'),
            self::account('MATRIZ', 'SANTANDER', '033', '2269', '13000434', '2', 2, '38'),
            self::account('MATRIZ', 'CEF', '104', '4316', '577498196', '3', 2, '68'),
            self::account('MATRIZ', 'CEF GERENCIAL', '104', '4316', '577213389', '2', 2, '120'),
            self::account('MATRIZ', 'BANRISUL', '041', '135', '68508360', '5', 2, '13'),
            self::account('MATRIZ', 'MATRIZ GERENCIAL BRB', '070', '050', '040580', '8', 2, '113'),

            self::account('FILIAL GO', 'BRB', '070', '050', '040580', '8', 2, '110'),
            self::account('FILIAL GO', 'BRB', '070', '046', '000132', '9', 2, '121'),
            self::account('FILIAL GO', 'SANTANDER', '033', '2269', '13000591', '0', 2, '54'),

            self::account('FILIAL MT', 'BRB', '070', '046', '000128', '0', 2, '35'),
            self::account('FILIAL MT', 'SANTANDER', '033', '2269', '13000596', '5', 2, '53'),
            self::account('FILIAL MT', 'BB', '001', '1231-9', '17740', '7', 2, '30'),

            self::account('FILIAL MG', 'BRB', '070', '046', '000125', '6', 2, '17'),
            self::account('FILIAL MG', 'SANTANDER', '033', '2269', '13004075', '8', 2, '86'),
            self::account('FILIAL SP', 'SANTANDER', '033', '2269', '13002387', '5', 2, '84'),

            self::account('APOIO', 'BRB', '070', '050', '040540', '9', 3, '116'),
            self::account('APOIO', 'SANTANDER', '033', '2269', '13000448', '3', 3, '40'),
            self::account('APOIO', 'CEF', '104', '4316', '577498753', '8', 3, '114'),
            self::account('APOIO', 'APOIO GERENCIAL BRB', '070', '050', '040622', '7', 3, '118'),

            self::account('SS SERVICOS', 'BRB', '070', '050', '040513', '1', 8, '106'),
            self::account('SS SERVICOS', 'CEF', '104', '4316', '577498752', '0', 8, '50'),

            self::account('LRB', 'BRB', '070', '060', '059135', '2', 4, '119'),
            self::account('LRB', 'CEF', '104', '4316', '577498751', '1', 4, '116'),

            self::account('STAR', 'BRB', '070', '050', '040457', '7', 11, '7'),
            self::account('STAR', 'BB', '001', '2901-7', '51356', '3', 11, '24'),

            self::account('MULTI', 'BRB', '070', '050', '040623', '5', 10, '14'),
            self::account('MULTI', 'BRB - ALUGUEL', '070', '050', '040464', '0', 10, '11'),
            self::account('MULTI', 'BB', '001', '1231-9', '112550', '8', 10, '05'),

            self::account('BEST', 'SANTANDER', '033', '2269', '13000945', '7', 7, '90'),
            self::account('BALUARTE', 'BRB', '070', '060', '059136', '0', 9, '08'),
        ];
    }

    /** @return array<string, int|string> */
    private static function account(
        string $unit,
        string $bankName,
        string $bankCode,
        string $agency,
        string $accountNumber,
        string $accountDigit,
        int $seniorCodEmp,
        string $seniorNumCco,
    ): array {
        return [
            'unit' => $unit,
            'bank_name' => $bankName,
            'bank_code' => $bankCode,
            'agency' => $agency,
            'account_number' => $accountNumber,
            'account_digit' => $accountDigit,
            'senior_codemp' => $seniorCodEmp,
            'senior_num_cco' => $seniorNumCco,
        ];
    }
}
