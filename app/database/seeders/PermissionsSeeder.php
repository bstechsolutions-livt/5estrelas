<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['key' => '*', 'label' => 'Acesso total (admin)', 'module' => 'sistema', 'description' => 'Concede todas as permissões do sistema.'],

            ['key' => 'usuarios.listar', 'label' => 'Listar usuários', 'module' => 'usuarios', 'description' => 'Visualizar a lista de usuários cadastrados.'],
            ['key' => 'usuarios.criar', 'label' => 'Criar usuários', 'module' => 'usuarios', 'description' => 'Cadastrar novos usuários no sistema.'],
            ['key' => 'usuarios.editar', 'label' => 'Editar usuários', 'module' => 'usuarios', 'description' => 'Alterar dados, departamento e filiais de acesso dos usuários.'],
            ['key' => 'usuarios.excluir', 'label' => 'Excluir usuários', 'module' => 'usuarios', 'description' => 'Desativar ou remover usuários.'],
            ['key' => 'usuarios.gerenciar_permissoes', 'label' => 'Gerenciar permissões', 'module' => 'usuarios', 'description' => 'Atribuir e remover permissões de outros usuários.'],
            ['key' => 'usuarios.impersonar', 'label' => 'Entrar como outro usuário', 'module' => 'usuarios', 'description' => 'Visualizar e operar o sistema em nome de outro usuário (suporte/auditoria).'],

            ['key' => 'aparencia.visualizar', 'label' => 'Ver aparência', 'module' => 'aparencia', 'description' => 'Acessar configurações visuais do sistema.'],
            ['key' => 'aparencia.editar', 'label' => 'Editar aparência', 'module' => 'aparencia', 'description' => 'Alterar logo, cores e identidade visual.'],

            ['key' => 'auditoria.visualizar', 'label' => 'Ver logs de auditoria', 'module' => 'auditoria', 'description' => 'Consultar histórico de ações no sistema.'],

            ['key' => 'noticias.gerenciar', 'label' => 'Gerenciar destaques e notícias', 'module' => 'noticias', 'description' => 'Publicar e editar notícias na intranet.'],

            ['key' => 'departamentos.gerenciar', 'label' => 'Gerenciar departamentos', 'module' => 'departamentos', 'description' => 'Cadastrar departamentos, gestores e vínculos de aprovação.'],

            ['key' => 'filiais.gerenciar', 'label' => 'Gerenciar filiais', 'module' => 'filiais', 'description' => 'Cadastrar filiais, apelidos e liberação de acesso por filial.'],

            ['key' => 'financeiro.contas_pagar.visualizar', 'label' => 'Ver contas a pagar', 'module' => 'financeiro', 'description' => 'Acessar listagem e detalhe de títulos a pagar.'],
            ['key' => 'financeiro.contas_pagar.lancar', 'label' => 'Lançar título manual (CP)', 'module' => 'financeiro', 'description' => 'Criar títulos de contas a pagar pela intranet (sem Senior).'],
            ['key' => 'financeiro.contas_pagar.preparar', 'label' => 'Preparar contas a pagar', 'module' => 'financeiro', 'description' => 'Anexar documentos, editar dados e montar títulos avulsos para envio.'],
            ['key' => 'financeiro.contas_pagar.aprovar', 'label' => 'Aprovar contas a pagar', 'module' => 'financeiro', 'description' => 'Aprovar títulos avulsos quando designado na etapa do fluxo.'],
            ['key' => 'financeiro.contas_pagar.alcada_gerenciar', 'label' => 'Gerenciar alçada do contas a pagar', 'module' => 'financeiro', 'description' => 'Configurar limites e regras de alçada de pagamento.'],
            ['key' => 'financeiro.ver_todos_departamentos', 'label' => 'Ver dados de todos os departamentos (financeiro)', 'module' => 'financeiro', 'description' => 'Ignorar restrição de departamento no módulo financeiro (CP, dashboard, autorizações, borderôs).'],
            ['key' => 'financeiro.contas_pagar.vincular_departamento_sync', 'label' => 'Vincular departamento em títulos aguardando sync', 'module' => 'financeiro', 'description' => 'Acessar a aba Aguardando sincronização e definir manualmente o departamento de títulos importados da Senior.'],
            ['key' => 'financeiro.contas_pagar.classificacao_gerenciar', 'label' => 'Gerenciar classificação por departamento (CP)', 'module' => 'financeiro', 'description' => 'Definir regras de centro de custo por departamento nos títulos.'],
            ['key' => 'financeiro.contas_pagar.ver_todos_departamentos', 'label' => 'Ver e filtrar títulos de todos os departamentos (CP)', 'module' => 'financeiro', 'description' => 'Alias legado de financeiro.ver_todos_departamentos na listagem de CP.'],
            ['key' => 'financeiro.contas_pagar.ver_todas_filiais', 'label' => 'Ver títulos de todas as filiais (CP)', 'module' => 'financeiro', 'description' => 'Visualizar títulos de CP de filiais além das liberadas ao usuário.'],
            ['key' => 'financeiro.contas_pagar.editar_vencimento', 'label' => 'Editar vencimento de contas a pagar (financeiro)', 'module' => 'financeiro', 'description' => 'Alterar data de vencimento de títulos no módulo financeiro.'],
            ['key' => 'financeiro.contas_pagar.enviar_aprovacao_urgente', 'label' => 'Enviar para aprovação fora do prazo de 72h (CP)', 'module' => 'financeiro', 'description' => 'Liberar envio para aprovação de títulos com vencimento em menos de 72 horas (casos urgentes).'],
            ['key' => 'financeiro.contas_pagar.prioridade_gerenciar', 'label' => 'Gerenciar prioridade e SLA de pagamento (CP)', 'module' => 'financeiro', 'description' => 'Definir prioridade e prazo (SLA) na etapa financeira de aprovação.'],
            ['key' => 'financeiro.contas_receber.visualizar', 'label' => 'Ver contas a receber', 'module' => 'financeiro', 'description' => 'Consultar títulos a receber espelhados da Senior (somente leitura).'],
            ['key' => 'financeiro.contas_receber.ver_todas_filiais', 'label' => 'Ver títulos a receber de todas as filiais (CR)', 'module' => 'financeiro', 'description' => 'Visualizar CR de todas as filiais além das liberadas ao usuário.'],
            ['key' => 'financeiro.plano_contas.visualizar', 'label' => 'Ver plano de contas', 'module' => 'financeiro', 'description' => 'Consultar plano de contas derivado de CP/CR.'],
            ['key' => 'financeiro.borderos.visualizar', 'label' => 'Ver borderôs', 'module' => 'financeiro', 'description' => 'Listar borderôs, ver detalhes e aprovar etapas quando for o aprovador designado.'],
            ['key' => 'financeiro.borderos.automatico_gerenciar', 'label' => 'Configurar e gerar borderôs automáticos', 'module' => 'financeiro', 'description' => 'Criar regras de agrupamento e agendar geração automática de borderôs pendentes.'],
            ['key' => 'financeiro.borderos.liberar_titulo', 'label' => 'Liberar título do borderô', 'module' => 'financeiro', 'description' => 'Durante aprovação, solta um título do borderô para seguir o fluxo avulso (com motivo obrigatório).'],
            ['key' => 'financeiro.borderos.expulsar_titulo', 'label' => 'Expulsar título do borderô', 'module' => 'financeiro', 'description' => 'Reprovar e remover um título do borderô em aprovação, devolvendo-o ao CP pendente avulso. Aprovadores da etapa também podem sem esta permissão.'],
            ['key' => 'financeiro.borderos.reprovar', 'label' => 'Reprovar borderô inteiro', 'module' => 'financeiro', 'description' => 'Devolver o borderô montado para pendente na lista de borderôs, sem desmanchar o pacote.'],
            ['key' => 'financeiro.borderos.desfazer', 'label' => 'Desfazer borderô', 'module' => 'financeiro', 'description' => 'Dissolver borderô pendente ou em preparação e liberar todos os títulos para CP avulso.'],
            ['key' => 'financeiro.conciliacao.visualizar', 'label' => 'Ver conciliação bancária', 'module' => 'financeiro', 'description' => 'Acessar módulo de conciliação com extratos OFX.'],
            ['key' => 'financeiro.bancos.visualizar', 'label' => 'Ver contas bancárias', 'module' => 'financeiro', 'description' => 'Listar contas bancárias cadastradas para conciliação.'],
            ['key' => 'financeiro.bancos.gerenciar', 'label' => 'Gerenciar contas bancárias', 'module' => 'financeiro', 'description' => 'Cadastrar, editar e ativar/desativar contas bancárias.'],
            ['key' => 'financeiro.workflows.configurar', 'label' => 'Configurar fluxos de aprovação', 'module' => 'financeiro', 'description' => 'Editar trilhas de aprovação por área/departamento.'],

            ['key' => 'backups.gerenciar', 'label' => 'Gerenciar backups', 'module' => 'backups', 'description' => 'Executar e restaurar backups do sistema.'],

            ['key' => 'contratos.visualizar', 'label' => 'Ver gestão de contratos', 'module' => 'contratos', 'description' => 'Acessar painel e indicadores de contratos.'],
            ['key' => 'contratos.gerenciar', 'label' => 'Gerenciar contratos (criar/editar/excluir)', 'module' => 'contratos', 'description' => 'Cadastrar e manter contratos e compromissos.'],

            ['key' => 'solicitacoes.visualizar', 'label' => 'Ver solicitações', 'module' => 'solicitacoes', 'description' => 'Acompanhar solicitações internas.'],
            ['key' => 'solicitacoes.criar', 'label' => 'Abrir solicitações', 'module' => 'solicitacoes', 'description' => 'Criar novas solicitações no sistema.'],
            ['key' => 'solicitacoes.configurar', 'label' => 'Configurar solicitações (assuntos/fluxos)', 'module' => 'solicitacoes', 'description' => 'Definir assuntos e fluxos de solicitações.'],
            ['key' => 'solicitacoes.aprovar', 'label' => 'Aprovar solicitações', 'module' => 'solicitacoes', 'description' => 'Aprovar ou reprovar solicitações pendentes.'],

            ['key' => 'comercial.visualizar', 'label' => 'Ver módulo comercial', 'module' => 'comercial', 'description' => 'Acessar propostas, cotações e contratos comerciais.'],
            ['key' => 'comercial.cotar', 'label' => 'Criar cotações/propostas', 'module' => 'comercial', 'description' => 'Montar e editar propostas comerciais.'],
            ['key' => 'comercial.aprovar', 'label' => 'Aprovar propostas', 'module' => 'comercial', 'description' => 'Aprovar propostas no fluxo comercial.'],
            ['key' => 'comercial.configurar', 'label' => 'Configurar comercial (CCT/valores/índices)', 'module' => 'comercial', 'description' => 'Parâmetros de precificação e índices do módulo comercial.'],
        ];

        foreach ($permissions as $row) {
            Permission::updateOrCreate(
                ['key' => $row['key']],
                $row,
            );
        }
    }
}
