<?php

namespace App\Console\Commands;

use App\Services\BankAccountImportService;
use App\Services\Senior\SeniorException;
use Illuminate\Console\Command;

class ImportSeniorBankAccounts extends Command
{
    protected $signature = 'senior:import-bank-accounts';

    protected $description = 'Carrega no Hub somente as contas da relação bancária oficial, cruzadas com a Senior.';

    public function handle(BankAccountImportService $importer): int
    {
        $this->info('Carregando relação oficial de contas bancárias...');

        try {
            $result = $importer->importFromSenior();
        } catch (SeniorException $e) {
            $this->error("Falha Senior ({$e->kind}): {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Senior: {$result['total_senior']} conta(s).");
        $this->info(
            "Criadas: {$result['created']} | Atualizadas: {$result['updated']} "
            ."| Removidas: {$result['removed']} | Fora da relação: {$result['skipped']}"
        );

        return self::SUCCESS;
    }
}
