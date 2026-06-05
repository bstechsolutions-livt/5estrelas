<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\v2\BsGestaoContrato;
use Illuminate\Database\Seeder;

/**
 * Dados de teste de "Serviços Prestados" (a 5 Estrelas presta ao cliente).
 * Idempotente: só cria se ainda não existir nenhum SERVICO_PRESTADO.
 */
class ServicosPrestadosSeeder extends Seeder
{
    public function run(): void
    {
        if (BsGestaoContrato::where('tipo', 'SERVICO_PRESTADO')->exists()) {
            $this->command->info('Serviços Prestados já existem, nada a fazer.');
            return;
        }

        $branches = Branch::pluck('id')->toArray();
        if (empty($branches)) {
            $this->command->warn('Sem filiais (branches).');
            return;
        }

        $servicos = ['Vigilância Patrimonial', 'Portaria 24h', 'Segurança de Eventos', 'Monitoramento Eletrônico', 'Rondas Motorizadas', 'Controle de Acesso', 'Brigada de Incêndio'];
        $clientes = ['Shopping Center Norte', 'Condomínio Residencial Aurora', 'Indústria Metalúrgica Goiás', 'Hospital Santa Clara', 'Faculdade UniCampus', 'Supermercados BomPreço', 'Banco Regional S.A.'];

        for ($i = 0; $i < 10; $i++) {
            $inicio = now()->subMonths(random_int(3, 24));
            BsGestaoContrato::create([
                'tipo' => 'SERVICO_PRESTADO',
                'filial_id' => $branches[array_rand($branches)],
                'tipo_servico' => $servicos[array_rand($servicos)],
                'descricao_servico' => 'Prestação de serviço de segurança para o cliente.',
                'razao_social_loja' => $clientes[array_rand($clientes)],
                'nome_locador' => $clientes[array_rand($clientes)],
                'documento_locador' => (string) random_int(10000000000000, 99999999999999),
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => (clone $inicio)->addMonths(random_int(12, 36))->toDateString(),
                'dia_vencimento' => random_int(1, 28),
                'valor_mensal' => random_int(5000, 60000) + random_int(0, 99) / 100,
                'numero_contrato' => 'CT-PRES-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'responsavel_interno' => 'Comercial',
                'status' => 'ATIVO',
                'created_by' => 2,
            ]);
        }

        $this->command->info('✅ Serviços Prestados: ' . BsGestaoContrato::where('tipo', 'SERVICO_PRESTADO')->count());
    }
}
