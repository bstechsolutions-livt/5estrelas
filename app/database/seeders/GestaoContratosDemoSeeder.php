<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\v2\BsGestaoAlvara;
use App\Models\v2\BsGestaoContrato;
use App\Models\v2\BsGestaoContratoReajuste;
use App\Models\v2\BsGestaoTipoAlvara;
use App\Models\v2\BsGestaoTipoIndice;
use Illuminate\Database\Seeder;

class GestaoContratosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::pluck('id')->toArray();
        if (empty($branches)) {
            $this->command->warn('Sem filiais (branches). Rode o seeder de filiais antes.');
            return;
        }

        $indices = BsGestaoTipoIndice::pluck('id')->toArray();
        $tiposAlvara = BsGestaoTipoAlvara::pluck('id')->toArray();

        $locadores = ['Imóveis Premium Ltda', 'João da Silva', 'Construtora Horizonte', 'Maria Aparecida Souza', 'Patrimonial Center', 'Edifício Comercial SA'];
        $servicos = ['Limpeza e Conservação', 'Vigilância Eletrônica', 'Manutenção Predial', 'Dedetização', 'Internet Link Dedicado', 'Telefonia Corporativa', 'Software de Gestão'];
        $cidades = ['Brasília', 'Goiânia', 'São Paulo', 'Anápolis', 'Aparecida de Goiânia'];
        $estados = ['DF', 'GO', 'SP'];

        // Contratos de LOCAÇÃO
        for ($i = 0; $i < 12; $i++) {
            $inicio = now()->subMonths(random_int(6, 36));
            $fim = (clone $inicio)->addMonths(random_int(12, 48));
            $valor = random_int(2500, 35000) + random_int(0, 99) / 100;

            $contrato = BsGestaoContrato::create([
                'tipo' => 'LOCACAO',
                'filial_id' => $branches[array_rand($branches)],
                'razao_social_loja' => '5 Estrelas Segurança - Unidade ' . ($i + 1),
                'cnpj_loja' => $this->fakeCnpj(),
                'tipo_pessoa' => random_int(0, 1) ? 'PJ' : 'PF',
                'nome_locador' => $locadores[array_rand($locadores)],
                'documento_locador' => $this->fakeCnpj(),
                'email_locador' => 'locador' . $i . '@exemplo.com.br',
                'telefone_locador' => '(61) 9' . random_int(1000, 9999) . '-' . random_int(1000, 9999),
                'imobiliaria' => random_int(0, 1) ? 'Imobiliária Central' : null,
                'endereco_imovel' => 'Quadra ' . random_int(1, 50) . ', Lote ' . random_int(1, 30),
                'cidade' => $cidades[array_rand($cidades)],
                'estado' => $estados[array_rand($estados)],
                'cep' => random_int(70000, 79999) . '-' . random_int(100, 999),
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
                'dia_vencimento' => random_int(1, 28),
                'valor_mensal' => $valor,
                'valor_condominio' => random_int(0, 1) ? random_int(300, 1500) : 0,
                'valor_iptu' => random_int(0, 1) ? random_int(100, 800) : 0,
                'tipo_indice_id' => $indices ? $indices[array_rand($indices)] : null,
                'data_proximo_reajuste' => now()->addMonths(random_int(1, 12))->toDateString(),
                'renovacao_automatica' => (bool) random_int(0, 1),
                'numero_contrato' => 'CT-LOC-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'responsavel_interno' => 'Departamento Jurídico',
                'status' => 'ATIVO',
                'created_by' => 2,
            ]);

            // Alguns reajustes históricos
            if (random_int(0, 1)) {
                BsGestaoContratoReajuste::create([
                    'contrato_id' => $contrato->id,
                    'data_reajuste' => now()->subMonths(random_int(2, 11))->toDateString(),
                    'valor_anterior' => $valor * 0.92,
                    'valor_reajustado' => $valor,
                    'percentual_aplicado' => 8.0,
                    'indice_utilizado' => 'IGPM',
                    'negociador' => 'Setor de Contratos',
                    'created_by' => 2,
                ]);
            }
        }

        // Contratos de SERVIÇO
        for ($i = 0; $i < 10; $i++) {
            $inicio = now()->subMonths(random_int(3, 24));
            $fim = (clone $inicio)->addMonths(random_int(12, 36));

            BsGestaoContrato::create([
                'tipo' => 'SERVICO',
                'filial_id' => $branches[array_rand($branches)],
                'tipo_servico' => $servicos[array_rand($servicos)],
                'descricao_servico' => 'Prestação de serviço continuado conforme contrato.',
                'nome_locador' => 'Fornecedor ' . $servicos[array_rand($servicos)],
                'documento_locador' => $this->fakeCnpj(),
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
                'dia_vencimento' => random_int(1, 28),
                'valor_mensal' => random_int(800, 18000) + random_int(0, 99) / 100,
                'numero_contrato' => 'CT-SRV-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'responsavel_interno' => 'Departamento de Compras',
                'status' => 'ATIVO',
                'created_by' => 2,
            ]);
        }

        // Alvarás (alguns vencidos / vencendo)
        for ($i = 0; $i < 8; $i++) {
            $validade = now()->addDays(random_int(-30, 180));
            BsGestaoAlvara::create([
                'filial_id' => $branches[array_rand($branches)],
                'tipo_alvara_id' => $tiposAlvara ? $tiposAlvara[array_rand($tiposAlvara)] : null,
                'numero_documento' => 'ALV-' . random_int(10000, 99999),
                'descricao' => 'Documento de licenciamento da unidade.',
                'orgao_emissor' => random_int(0, 1) ? 'Prefeitura' : 'Corpo de Bombeiros',
                'data_emissao' => now()->subMonths(random_int(6, 24))->toDateString(),
                'data_validade' => $validade->toDateString(),
                'status' => $validade->isPast() ? 'VENCIDO' : 'VIGENTE',
                'responsavel_renovacao' => 'Setor Administrativo',
                'custo_renovacao' => random_int(200, 2500),
                'created_by' => 2,
            ]);
        }

        $this->command->info('✅ Contratos: ' . BsGestaoContrato::count() . ' | Alvarás: ' . BsGestaoAlvara::count());
    }

    private function fakeCnpj(): string
    {
        return (string) random_int(10000000000000, 99999999999999);
    }
}
