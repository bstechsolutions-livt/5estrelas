<?php

namespace Database\Seeders;

use App\Models\v2\BsGestaoTipoAlvara;
use App\Models\v2\BsGestaoTipoIndice;
use Illuminate\Database\Seeder;

class GestaoContratosTiposSeeder extends Seeder
{
    /**
     * Seeder para popular os tipos de índice e tipos de alvará
     * utilizados no módulo de Gestão de Contratos e Alvarás (Demanda #908)
     */
    public function run(): void
    {
        $this->seedTiposIndice();
        $this->seedTiposAlvara();
    }

    private function seedTiposIndice(): void
    {
        $indices = [
            ['codigo' => 'IGPM', 'nome' => 'IGPM', 'descricao' => 'Índice Geral de Preços do Mercado', 'ativo' => true],
            ['codigo' => 'IPCA', 'nome' => 'IPCA', 'descricao' => 'Índice de Preços ao Consumidor Amplo', 'ativo' => true],
            ['codigo' => 'INPC', 'nome' => 'INPC', 'descricao' => 'Índice Nacional de Preços ao Consumidor', 'ativo' => true],
            ['codigo' => 'INCC', 'nome' => 'INCC', 'descricao' => 'Índice Nacional de Custo da Construção', 'ativo' => true],
            ['codigo' => 'IGP-DI', 'nome' => 'IGP-DI', 'descricao' => 'Índice Geral de Preços - Disponibilidade Interna', 'ativo' => true],
            ['codigo' => 'FIXO', 'nome' => 'Fixo conforme contrato', 'descricao' => 'Reajuste fixo conforme cláusula contratual', 'ativo' => true],
        ];

        foreach ($indices as $indice) {
            BsGestaoTipoIndice::firstOrCreate(
                ['codigo' => $indice['codigo']],
                $indice
            );
        }

        $this->command->info('Tipos de Índice: ' . BsGestaoTipoIndice::count() . ' registros');
    }

    private function seedTiposAlvara(): void
    {
        $tipos = [
            ['codigo' => 'FUNCIONAMENTO', 'nome' => 'Alvará de Funcionamento', 'descricao' => 'Alvará de Funcionamento emitido pela Prefeitura', 'ativo' => true],
            ['codigo' => 'BOMBEIROS', 'nome' => 'Alvará do Corpo de Bombeiros (AVCB) - Cercon', 'descricao' => 'Alvará do Corpo de Bombeiros (AVCB) - Cercon', 'ativo' => true],
            ['codigo' => 'SANITARIO', 'nome' => 'Alvará Sanitário', 'descricao' => 'Licença Sanitária emitida pela Vigilância Sanitária', 'ativo' => true],
            ['codigo' => 'AMBIENTAL', 'nome' => 'Licença Ambiental', 'descricao' => 'Licença Ambiental de Operação', 'ativo' => true],
            ['codigo' => 'PUBLICIDADE', 'nome' => 'Alvará de Publicidade', 'descricao' => 'Autorização para instalação de placas e letreiros', 'ativo' => true],
            // Notificações
            ['codigo' => 'NOTIFICACAO_VIGILANCIA', 'nome' => 'Notificação Vigilância Sanitária', 'descricao' => 'Notificação emitida pela Vigilância Sanitária', 'ativo' => true],
            ['codigo' => 'NOTIFICACAO_PREFEITURA', 'nome' => 'Notificação Prefeitura', 'descricao' => 'Notificação emitida pela Prefeitura', 'ativo' => true],
            ['codigo' => 'NOTIFICACAO_CERCON', 'nome' => 'Notificação Cercon', 'descricao' => 'Notificação emitida pelo Cercon (Corpo de Bombeiros)', 'ativo' => true],
            // Auto de Infração
            ['codigo' => 'INFRACAO_VIGILANCIA', 'nome' => 'Auto de Infração Vigilância', 'descricao' => 'Auto de Infração emitido pela Vigilância Sanitária', 'ativo' => true],
            ['codigo' => 'INFRACAO_PREFEITURA', 'nome' => 'Auto de Infração Prefeitura', 'descricao' => 'Auto de Infração emitido pela Prefeitura', 'ativo' => true],
            ['codigo' => 'INFRACAO_CERCON', 'nome' => 'Auto de Infração Cercon', 'descricao' => 'Auto de Infração emitido pelo Cercon (Corpo de Bombeiros)', 'ativo' => true],
            // Outros
            ['codigo' => 'OUTROS', 'nome' => 'Outros', 'descricao' => 'Outras licenças e autorizações', 'ativo' => true],
        ];

        foreach ($tipos as $tipo) {
            BsGestaoTipoAlvara::updateOrCreate(
                ['codigo' => $tipo['codigo']],
                $tipo
            );
        }

        $this->command->info('Tipos de Alvará: ' . BsGestaoTipoAlvara::count() . ' registros');
    }
}
