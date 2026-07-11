<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BorderoController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BankConciliationController;
use App\Http\Controllers\PayableController;
use App\Http\Controllers\PayableAlcadaController;
use App\Http\Controllers\PayableDepartmentRulesController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostInteractionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\UserShortcutController;
use App\Http\Controllers\v2\GestaoContratosController;
use App\Http\Controllers\v2\GestaoEquipamentosController;
use App\Http\Controllers\SolicitacoesController;
use App\Http\Controllers\Comercial\ComercialClienteController;
use App\Http\Controllers\Comercial\ComercialConfigController;
use App\Http\Controllers\Comercial\ComercialCotacaoController;
use App\Http\Controllers\Comercial\ComercialFaturamentoController;
use App\Http\Controllers\Comercial\ComercialPropostaController;
use App\Http\Controllers\Comercial\ComercialReajusteController;
use App\Http\Controllers\Comercial\ComercialSaudeController;
use App\Http\Controllers\Comercial\ComercialContratoController;
use App\Http\Controllers\Comercial\ComercialDashboardController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    // Recuperação de senha
    Route::get('/esqueci-senha', [PasswordResetLinkController::class, 'show'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'show'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])->name('password.update');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Perfil
    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/perfil', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/perfil/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

    // Atalhos do usuário
    Route::put('/perfil/atalhos', [UserShortcutController::class, 'update'])->name('shortcuts.update');

    // Interações em posts (qualquer autenticado)
    Route::post('/posts/{id}/like', [PostInteractionController::class, 'toggleLike'])->name('posts.like');
    Route::get('/posts/{id}/comentarios', [PostInteractionController::class, 'comments']);
    Route::post('/posts/{id}/comentarios', [PostInteractionController::class, 'storeComment']);
    Route::delete('/posts/{postId}/comentarios/{commentId}', [PostInteractionController::class, 'destroyComment']);
    Route::get('/feed', [PostInteractionController::class, 'feed']);

    // Usuários
    Route::middleware('permission:usuarios.listar')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('permission:usuarios.criar')->group(function () {
        Route::get('/usuarios/criar', [UserController::class, 'create'])->name('users.create');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:usuarios.editar')->group(function () {
        Route::get('/usuarios/{id}/editar', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/usuarios/{id}', [UserController::class, 'update'])->name('users.update');
        Route::post('/usuarios/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::post('/usuarios/{id}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
    });
    Route::middleware('permission:usuarios.excluir')->group(function () {
        Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    });
    Route::middleware('permission:usuarios.gerenciar_permissoes')->group(function () {
        Route::get('/usuarios/{id}/permissoes', [UserPermissionController::class, 'edit'])->name('users.permissions.edit');
        Route::put('/usuarios/{id}/permissoes', [UserPermissionController::class, 'update'])->name('users.permissions.update');
    });

    // Notícias / Destaques (admin)
    Route::middleware('permission:noticias.gerenciar')->group(function () {
        Route::get('/noticias', [PostController::class, 'index'])->name('posts.index');
        Route::get('/noticias/criar', [PostController::class, 'create'])->name('posts.create');
        Route::post('/noticias', [PostController::class, 'store'])->name('posts.store');
        Route::get('/noticias/{id}/editar', [PostController::class, 'edit'])->name('posts.edit');
        Route::put('/noticias/{id}', [PostController::class, 'update'])->name('posts.update');
        Route::post('/noticias/{id}/toggle-active', [PostController::class, 'toggleActive']);
        Route::delete('/noticias/{id}', [PostController::class, 'destroy'])->name('posts.destroy');
    });

    // Aparência
    Route::middleware('permission:aparencia.editar')->group(function () {
        Route::get('/settings/aparencia', [SettingsController::class, 'appearance'])->name('settings.appearance');
        Route::post('/settings/aparencia', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.update');
    });

    // Auditoria
    Route::middleware('permission:auditoria.visualizar')->group(function () {
        Route::get('/auditoria', [AuditLogController::class, 'index'])->name('audit.index');
    });

    // Backups
    Route::middleware('permission:backups.gerenciar')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/run', [BackupController::class, 'run'])->name('backups.run');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])
            ->where('filename', '.*\.zip')
            ->name('backups.download');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])
            ->where('filename', '.*\.zip')
            ->name('backups.destroy');
    });

    // Departamentos
    Route::middleware('permission:departamentos.gerenciar')->group(function () {
        Route::get('/departamentos', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('/departamentos/criar', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('/departamentos', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('/departamentos/{id}/editar', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('/departamentos/{id}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departamentos/{id}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });

    // Filiais
    Route::middleware('permission:filiais.gerenciar')->group(function () {
        Route::get('/filiais', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/filiais/criar', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/filiais', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/filiais/{id}/editar', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/filiais/{id}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/filiais/{id}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });

    // Financeiro - Contas a Pagar - Alçada (gestão de quem paga/concilia/assina)
    Route::get('financeiro/dashboard', [\App\Http\Controllers\FinanceiroDashboardController::class, 'index'])
        ->middleware('permission:financeiro.contas_pagar.visualizar')
        ->name('financeiro.dashboard.index');
 // Minhas Pendências de Aprovação (Financeiro)
 Route::get("financeiro/pendencias", [\App\Http\Controllers\ApprovalPendingController::class, "index"])->name("approval-pending.index");
 Route::get("financeiro/fluxos-aprovacao", [\App\Http\Controllers\ApprovalFlowConfigController::class, "index"])->middleware("permission:financeiro.workflows.configurar")->name("approval-flow-config.index");
 Route::post("financeiro/fluxos-aprovacao", [\App\Http\Controllers\ApprovalFlowConfigController::class, "update"])->middleware("permission:financeiro.workflows.configurar")->name("approval-flow-config.update");
    Route::prefix('financeiro/contas-pagar/alcada')->middleware('permission:financeiro.contas_pagar.alcada_gerenciar')->group(function () {
        Route::get('/', [PayableAlcadaController::class, 'index'])->name('payables.alcada.index');
        Route::post('/', [PayableAlcadaController::class, 'store'])->name('payables.alcada.store');
        Route::delete('/{role}/{userId}', [PayableAlcadaController::class, 'destroy'])->whereNumber('userId')->name('payables.alcada.destroy');
    });
    Route::prefix('financeiro/contas-pagar/classificacao-departamentos')->middleware('permission:financeiro.contas_pagar.classificacao_gerenciar')->group(function () {
        Route::get('/', [PayableDepartmentRulesController::class, 'index'])->name('payables.department-rules.index');
        Route::post('/', [PayableDepartmentRulesController::class, 'update'])->name('payables.department-rules.update');
    });

    // Financeiro - Conciliação Bancária (OFX) — ANTES do grupo genérico /financeiro/contas-pagar
    Route::prefix('financeiro/contas-pagar/conciliacao')->middleware('permission:financeiro.conciliacao.visualizar')->group(function () {
        Route::get('/', [BankConciliationController::class, 'index'])->name('bank-conciliation.index');
        Route::get('/search-payables', [BankConciliationController::class, 'searchPayables'])->name('bank-conciliation.search-payables');
        Route::get('/{importId}', [BankConciliationController::class, 'show'])->whereNumber('importId')->name('bank-conciliation.show');
        Route::post('/upload', [BankConciliationController::class, 'upload'])->name('bank-conciliation.upload');
        Route::post('/transactions/{id}/accept', [BankConciliationController::class, 'accept'])->whereNumber('id')->name('bank-conciliation.accept');
        Route::post('/transactions/{id}/reject', [BankConciliationController::class, 'reject'])->whereNumber('id')->name('bank-conciliation.reject');
        Route::post('/transactions/{id}/link', [BankConciliationController::class, 'link'])->whereNumber('id')->name('bank-conciliation.link');
        Route::post('/{importId}/batch-conciliate', [BankConciliationController::class, 'batchConciliate'])->whereNumber('importId')->name('bank-conciliation.batch');
        Route::delete('/{importId}', [BankConciliationController::class, 'destroy'])->whereNumber('importId')->name('bank-conciliation.destroy');
    });

    // Financeiro - Contas a Pagar
    Route::prefix('financeiro/contas-pagar')->middleware('permission:financeiro.contas_pagar.visualizar')->group(function () {
        Route::get('/', [PayableController::class, 'index'])->name('payables.index');
        Route::get('/{id}', [PayableController::class, 'show'])->whereNumber('id')->name('payables.show');
        Route::post('/{id}/comentarios', [PayableController::class, 'addComment'])->name('payables.comment');
        Route::post('/{id}/documentos', [PayableController::class, 'addDocument'])->name('payables.document');
        Route::delete('/{payableId}/documentos/{docId}', [PayableController::class, 'removeDocument'])->name('payables.document.remove');
        Route::post('/{id}/vencimento', [PayableController::class, 'updateDueDate'])->whereNumber('id')->name('payables.due_date');
        Route::post('/{id}/prioridade', [PayableController::class, 'updatePaymentPriority'])->whereNumber('id')->name('payables.priority');
        Route::post('/{id}/enviar-aprovacao', [PayableController::class, 'sendForApproval'])->name('payables.send_approval');
        Route::post('/{id}/aprovar', [PayableController::class, 'approve'])->name('payables.approve');
        Route::post('/{id}/reprovar', [PayableController::class, 'reject'])->name('payables.reject');
        Route::post('/{id}/registrar-pagamento', [PayableController::class, 'pay'])->whereNumber('id')->name('payables.pay');
        Route::post('/{id}/conciliar', [PayableController::class, 'conciliate'])->whereNumber('id')->name('payables.conciliate');
        Route::post('/{id}/divergencia', [PayableController::class, 'diverge'])->whereNumber('id')->name('payables.diverge');
        Route::get('/{id}/mentionable-users', [PayableController::class, 'mentionableUsers'])->whereNumber('id')->name('payables.mentionable');
        Route::post('/{id}/encerrar', [PayableController::class, 'finalSign'])->whereNumber('id')->name('payables.final-sign');
    });

    // Financeiro - Borderôs
    Route::prefix('financeiro/borderos')->middleware('permission:financeiro.borderos.visualizar')->group(function () {
        Route::get('/automatico', [\App\Http\Controllers\BorderoAutoConfigController::class, 'index'])
            ->middleware('permission:financeiro.borderos.automatico_gerenciar')
            ->name('borderos.auto-config.index');
        Route::post('/automatico', [\App\Http\Controllers\BorderoAutoConfigController::class, 'update'])
            ->middleware('permission:financeiro.borderos.automatico_gerenciar')
            ->name('borderos.auto-config.update');
        Route::post('/automatico/gerar', [\App\Http\Controllers\BorderoAutoConfigController::class, 'generate'])
            ->middleware('permission:financeiro.borderos.automatico_gerenciar')
            ->name('borderos.auto-config.generate');
        Route::get('/', [BorderoController::class, 'index'])->name('borderos.index');
        Route::post('/', [BorderoController::class, 'store'])->name('borderos.store');
        Route::get('/{id}', [BorderoController::class, 'show'])->name('borderos.show');
        Route::delete('/{borderoId}/titulos/{payableId}', [BorderoController::class, 'removePayable'])->name('borderos.remove_payable');
        Route::post('/{id}/enviar-aprovacao', [BorderoController::class, 'sendForApproval'])->name('borderos.send_approval');
        Route::post('/{id}/aprovar', [BorderoController::class, 'approve'])->name('borderos.approve');
        Route::post('/{id}/reprovar', [BorderoController::class, 'reject'])->name('borderos.reject');
    });

    // Notificações (do usuário autenticado, sem permissão extra)
    Route::prefix('notificacoes')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/contador', [NotificationController::class, 'unreadCount'])->name('notifications.count');
        Route::post('/{id}/lida', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/marcar-todas', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    });

    // Device tokens (push mobile)
    Route::prefix('device-tokens')->group(function () {
        Route::post('/', [DeviceTokenController::class, 'register'])->name('device_tokens.register');
        Route::delete('/', [DeviceTokenController::class, 'unregister'])->name('device_tokens.unregister');
    });

    // Busca global
    Route::get('/search', \App\Http\Controllers\SearchController::class)->name('search');

    // ═══════════════════════════════════════════════════════════════
    //   GESTÃO DE CONTRATOS (portado da intranet Biglar)
    // ═══════════════════════════════════════════════════════════════

    // Páginas (Inertia) — caminhos batem com a navegação interna das telas (/pagina/...)
    Route::prefix('pagina/gestao-contratos')->middleware('permission:contratos.visualizar')->name('gestao-contratos.')->group(function () {
        Route::get('/', fn () => Inertia::render('v2/gestao-contratos/index'))->name('dashboard');
        Route::get('/locacao', fn () => Inertia::render('v2/gestao-contratos/locacao/index'))->name('locacao');
        Route::get('/locacao/novo', fn () => Inertia::render('v2/gestao-contratos/locacao/form'))->name('locacao.novo');
        Route::get('/locacao/{id}', fn ($id) => Inertia::render('v2/gestao-contratos/locacao/form', ['id' => $id]))->name('locacao.editar');
        Route::get('/servicos', fn () => Inertia::render('v2/gestao-contratos/servicos/index'))->name('servicos');
        Route::get('/servicos/novo', fn () => Inertia::render('v2/gestao-contratos/servicos/form'))->name('servicos.novo');
        Route::get('/servicos/{id}', fn ($id) => Inertia::render('v2/gestao-contratos/servicos/form', ['id' => $id]))->name('servicos.editar');
        // Serviços Prestados (5E presta ao cliente)
        Route::get('/servicos-prestados', fn () => Inertia::render('v2/gestao-contratos/servicos-prestados/index'))->name('servicos-prestados');
        Route::get('/servicos-prestados/novo', fn () => Inertia::render('v2/gestao-contratos/servicos-prestados/form'))->name('servicos-prestados.novo');
        Route::get('/servicos-prestados/{id}', fn ($id) => Inertia::render('v2/gestao-contratos/servicos-prestados/form', ['id' => $id]))->name('servicos-prestados.editar');
        Route::get('/alvaras', fn () => Inertia::render('v2/gestao-contratos/alvaras/index'))->name('alvaras');
        Route::get('/alvaras/novo', fn () => Inertia::render('v2/gestao-contratos/alvaras/form'))->name('alvaras.novo');
        Route::get('/alvaras/{id}', fn ($id) => Inertia::render('v2/gestao-contratos/alvaras/form', ['id' => $id]))->name('alvaras.editar');
        // Equipamentos
        Route::get('/equipamentos/dashboard', fn () => Inertia::render('v2/gestao-contratos/equipamentos/dashboard'))->name('equipamentos.dashboard');
        Route::get('/equipamentos', fn () => Inertia::render('v2/gestao-contratos/equipamentos/index'))->name('equipamentos');
        Route::get('/equipamentos/novo', fn () => Inertia::render('v2/gestao-contratos/equipamentos/form'))->name('equipamentos.novo');
        Route::get('/equipamentos/tipos', fn () => Inertia::render('v2/gestao-contratos/equipamentos/tipos/index'))->name('equipamentos.tipos');
        Route::get('/equipamentos/{id}', fn ($id) => Inertia::render('v2/gestao-contratos/equipamentos/form', ['id' => $id]))->name('equipamentos.editar');
    });

    // API (axios) — espelha os endpoints do GestaoContratosController
    Route::prefix('v2/gestao-contratos')->controller(GestaoContratosController::class)->middleware('permission:contratos.visualizar')->name('gestao-contratos.api.')->group(function () {
        Route::get('/dashboard', 'getDashboard')->name('dashboard');
        Route::get('/tipos-indice', 'getTiposIndice')->name('tipos-indice');
        Route::get('/tipos-alvara', 'getTiposAlvara')->name('tipos-alvara');
        Route::get('/filiais', 'getFiliais')->name('filiais');

        // Contratos
        Route::get('/contratos', 'getContratos')->name('contratos.index');
        Route::get('/contratos/exportar', 'exportarContratos')->name('contratos.exportar');
        Route::get('/contratos/{id}', 'getContrato')->name('contratos.show');
        Route::post('/contratos', 'storeContrato')->name('contratos.store');
        Route::put('/contratos/{id}', 'updateContrato')->name('contratos.update');
        Route::delete('/contratos/{id}', 'deleteContrato')->name('contratos.destroy');
        Route::post('/contratos/{contratoId}/reajustes', 'storeReajuste')->name('contratos.reajustes.store');
        Route::post('/contratos/{contratoId}/anexos', 'uploadAnexo')->name('contratos.anexos.store');
        Route::delete('/anexos/{id}', 'deleteAnexo')->name('anexos.destroy');

        // Alvarás
        Route::get('/alvaras', 'getAlvaras')->name('alvaras.index');
        Route::get('/alvaras/exportar', 'exportarAlvaras')->name('alvaras.exportar');
        Route::get('/alvaras/{id}', 'getAlvara')->name('alvaras.show');
        Route::post('/alvaras', 'storeAlvara')->name('alvaras.store');
        Route::put('/alvaras/{id}', 'updateAlvara')->name('alvaras.update');
        Route::delete('/alvaras/{id}', 'deleteAlvara')->name('alvaras.destroy');
        Route::post('/alvaras/{id}/anexo', 'uploadAnexoAlvara')->name('alvaras.anexo.store');
        Route::delete('/alvaras/{id}/anexo', 'deleteAnexoAlvara')->name('alvaras.anexo.destroy');
        Route::get('/alvaras/{id}/anexo/download', 'downloadAnexoAlvara')->name('alvaras.anexo.download');
    });

    // API Equipamentos
    Route::prefix('v2/gestao-contratos')->controller(GestaoEquipamentosController::class)->middleware('permission:contratos.visualizar')->name('gestao-contratos.api.equip.')->group(function () {
        Route::get('/equipamentos/dashboard', 'getDashboard')->name('dashboard');
        Route::get('/equipamentos/exportar', 'exportarEquipamentos')->name('exportar');
        Route::get('/equipamentos', 'getEquipamentos')->name('index');
        Route::get('/equipamentos/{id}', 'getEquipamento')->name('show');
        Route::post('/equipamentos', 'storeEquipamento')->name('store');
        Route::put('/equipamentos/{id}', 'updateEquipamento')->name('update');
        Route::delete('/equipamentos/{id}', 'deleteEquipamento')->name('destroy');
        // Tipos de equipamento
        Route::get('/tipos-equipamento', 'getTiposEquipamento')->name('tipos.index');
        Route::post('/tipos-equipamento', 'storeTipoEquipamento')->name('tipos.store');
        Route::put('/tipos-equipamento/{id}', 'updateTipoEquipamento')->name('tipos.update');
        Route::delete('/tipos-equipamento/{id}', 'deleteTipoEquipamento')->name('tipos.destroy');
        // Ocorrências
        Route::get('/equipamentos/{id}/ocorrencias', 'getOcorrencias')->name('ocorrencias.index');
        Route::post('/equipamentos/{id}/ocorrencias', 'storeOcorrencia')->name('ocorrencias.store');
        // Tratativas
        Route::get('/equipamentos/{id}/tratativas', 'getTratativas')->name('tratativas.index');
        Route::post('/equipamentos/{id}/tratativas', 'storeTratativa')->name('tratativas.store');
        // Fotos
        Route::post('/equipamentos/{id}/fotos', 'uploadFoto')->name('fotos.store');
        Route::post('/ocorrencias/{id}/fotos', 'uploadFotoOcorrencia')->name('ocorrencias.fotos.store');
        Route::delete('/equipamento-fotos/{id}', 'deleteFoto')->name('fotos.destroy');
        Route::get('/equipamento-fotos/{id}/download', 'downloadFoto')->name('fotos.download');
    });

    // ═══════════════════════════════════════════════════════════════
    //   COMERCIAL ("Gestão 360º") — esteira comercial / precificação IN 05
    //   Spec 1: Configuração / Valores (CCT, categorias, escalas, índices)
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('comercial')->middleware('permission:comercial.visualizar')->name('comercial.')->group(function () {
        // Dashboard do Comercial
        Route::get('dashboard', [ComercialDashboardController::class, 'index'])->name('dashboard');

        // Cotação (planilha IN 05)
        Route::get('cotacao', [ComercialCotacaoController::class, 'index'])->name('cotacao');
        Route::get('cotacao/dados', [ComercialCotacaoController::class, 'dados'])->name('cotacao.dados');
        Route::post('cotacao/calcular', [ComercialCotacaoController::class, 'calcular'])->name('cotacao.calcular');
        Route::post('cotacao/calcular-5e', [ComercialCotacaoController::class, 'calcular5e'])->name('cotacao.calcular5e');

        // Propostas — Controle de Propostas (listagem/funil) + salvar cotação
        Route::get('propostas', [ComercialPropostaController::class, 'index'])->name('propostas');
        Route::get('propostas/dados', [ComercialPropostaController::class, 'dados'])->name('propostas.dados');
        Route::post('propostas', [ComercialPropostaController::class, 'store'])->middleware('permission:comercial.cotar')->name('propostas.store');
        Route::post('propostas/manual', [ComercialPropostaController::class, 'storeManual'])->middleware('permission:comercial.cotar')->name('propostas.manual');
        Route::put('propostas/{id}', [ComercialPropostaController::class, 'update'])->middleware('permission:comercial.cotar')->name('propostas.update');
        Route::patch('propostas/{id}/situacao', [ComercialPropostaController::class, 'updateSituacao'])->middleware('permission:comercial.aprovar')->name('propostas.situacao');
        Route::delete('propostas/{id}', [ComercialPropostaController::class, 'destroy'])->middleware('permission:comercial.cotar')->name('propostas.destroy');

        // Configuração / Valores
        Route::get('configuracoes', [ComercialConfigController::class, 'index'])->name('configuracoes');
        Route::prefix('configuracoes')->name('config.')->group(function () {
            Route::get('dados', [ComercialConfigController::class, 'dados'])->name('dados');

            // Escrita de configuração (CCT/valores/índices) — só admin (comercial.configurar)
            Route::middleware('permission:comercial.configurar')->group(function () {
                // CCT
                Route::post('ccts', [ComercialConfigController::class, 'storeCct'])->name('ccts.store');
                Route::put('ccts/{id}', [ComercialConfigController::class, 'updateCct'])->name('ccts.update');
                Route::delete('ccts/{id}', [ComercialConfigController::class, 'destroyCct'])->name('ccts.destroy');

                // Estados (cria UF com CCTs padrão)
                Route::post('estados', [ComercialConfigController::class, 'storeEstado'])->name('estados.store');

                // Categorias
                Route::post('categorias', [ComercialConfigController::class, 'storeCategoria'])->name('categorias.store');
                Route::put('categorias/{id}', [ComercialConfigController::class, 'updateCategoria'])->name('categorias.update');
                Route::delete('categorias/{id}', [ComercialConfigController::class, 'destroyCategoria'])->name('categorias.destroy');

                // Escalas
                Route::post('escalas', [ComercialConfigController::class, 'storeEscala'])->name('escalas.store');
                Route::put('escalas/{id}', [ComercialConfigController::class, 'updateEscala'])->name('escalas.update');
                Route::delete('escalas/{id}', [ComercialConfigController::class, 'destroyEscala'])->name('escalas.destroy');

                // Índices
                Route::post('indices', [ComercialConfigController::class, 'salvarIndices'])->name('indices.salvar');

                // Encargos (detalhamento A/B/C/D)
                Route::post('encargos', [ComercialConfigController::class, 'salvarEncargos'])->name('encargos.salvar');

                // Insumos (global)
                Route::post('insumos', [ComercialConfigController::class, 'salvarInsumos'])->name('insumos.salvar');

                // Filiais / Empresas (espelhadas da Senior — sem criação/exclusão manual)
                Route::post('filiais/sincronizar', [ComercialConfigController::class, 'sincronizarFiliais'])->name('filiais.sincronizar');
                Route::put('filiais/{id}', [ComercialConfigController::class, 'updateFilial'])->name('filiais.update');
                Route::patch('filiais/{id}/toggle', [ComercialConfigController::class, 'toggleFilial'])->name('filiais.toggle');
            });
        });

        // Faturamento
        Route::get('faturamento', [ComercialFaturamentoController::class, 'index'])->name('faturamento');
        Route::get('faturamento/dados', [ComercialFaturamentoController::class, 'dados'])->name('faturamento.dados');
        Route::post('faturamento/salvar', [ComercialFaturamentoController::class, 'salvar'])->middleware('permission:comercial.cotar')->name('faturamento.salvar');
        Route::post('faturamento/local', [ComercialFaturamentoController::class, 'adicionarLocal'])->middleware('permission:comercial.cotar')->name('faturamento.local');
        Route::delete('faturamento/{id}', [ComercialFaturamentoController::class, 'excluirLocal'])->middleware('permission:comercial.cotar')->name('faturamento.excluir');

        // Clientes/Contratos
        Route::get('clientes', [ComercialClienteController::class, 'index'])->name('clientes');
        Route::get('clientes/{id}', [ComercialClienteController::class, 'show'])->name('clientes.show');
        Route::post('clientes', [ComercialClienteController::class, 'store'])->middleware('permission:comercial.cotar')->name('clientes.store');
        Route::put('clientes/{id}', [ComercialClienteController::class, 'update'])->middleware('permission:comercial.cotar')->name('clientes.update');
        Route::delete('clientes/{id}', [ComercialClienteController::class, 'destroy'])->middleware('permission:comercial.cotar')->name('clientes.destroy');
        Route::post('clientes/{id}/vincular', [ComercialClienteController::class, 'vincularProposta'])->middleware('permission:comercial.cotar')->name('clientes.vincular');
        Route::delete('clientes/{id}/desvincular/{propostaId}', [ComercialClienteController::class, 'desvincularProposta'])->middleware('permission:comercial.cotar')->name('clientes.desvincular');

        // Reajustes de contrato
        Route::get('reajustes', [ComercialReajusteController::class, 'index'])->name('reajustes');
        Route::get('reajustes/dados', [ComercialReajusteController::class, 'dados'])->name('reajustes.dados');
        Route::post('reajustes', [ComercialReajusteController::class, 'store'])->middleware('permission:comercial.cotar')->name('reajustes.store');
        Route::put('reajustes/{id}', [ComercialReajusteController::class, 'update'])->middleware('permission:comercial.cotar')->name('reajustes.update');
        Route::patch('reajustes/{id}/status', [ComercialReajusteController::class, 'updateStatus'])->middleware('permission:comercial.aprovar')->name('reajustes.status');
        Route::delete('reajustes/{id}', [ComercialReajusteController::class, 'destroy'])->middleware('permission:comercial.cotar')->name('reajustes.destroy');

        // Saúde Contratual
        Route::get('saude', [ComercialSaudeController::class, 'index'])->name('saude');

        // Contratos Ativos
        Route::get('contratos', [ComercialContratoController::class, 'index'])->name('contratos');
        Route::get('saude/{clienteId}/dados', [ComercialSaudeController::class, 'dados'])->name('saude.dados');
        Route::post('saude/{clienteId}/lancamento', [ComercialSaudeController::class, 'storeLancamento'])->middleware('permission:comercial.cotar')->name('saude.lancamento.store');
        Route::delete('saude/{clienteId}/lancamento/{lancId}', [ComercialSaudeController::class, 'destroyLancamento'])->middleware('permission:comercial.cotar')->name('saude.lancamento.destroy');
        Route::post('saude/{clienteId}/metas', [ComercialSaudeController::class, 'storeMetas'])->middleware('permission:comercial.configurar')->name('saude.metas');
    });

    // ═══════════════════════════════════════════════════════════════
    //   SOLICITAÇÕES (portado da intranet Biglar) — caminhos = /solicitacoes/*
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('solicitacoes')->middleware('permission:solicitacoes.visualizar')->group(function () {
        Route::get('possui-resolvidas', [SolicitacoesController::class, 'possuiResolvidas']);

        // Redirect legado /fila → /lista (URL antiga da Biglar)
        Route::get('fila', fn () => redirect('/solicitacoes/lista'))->name('solicitacoes.fila.redirect');

        // Configuração (admin)
        Route::prefix('configuracoes')->middleware('permission:solicitacoes.configurar')->group(function () {
            Route::get('', [SolicitacoesController::class, 'indexConfiguracoes']);
            Route::get('departamentos', [SolicitacoesController::class, 'getDepartamentos']);
            Route::get('buscar-equipamentos', [SolicitacoesController::class, 'getEquipamentos']);
            Route::get('canais-notif', [SolicitacoesController::class, 'getCanaisNotif']);
            Route::get('verificar-respostas-campo/{selecao_id}', [SolicitacoesController::class, 'verificarRespostasCampo']);
            Route::post('departamentos', [SolicitacoesController::class, 'storeDepartamentos']);
            Route::post('assuntos', [SolicitacoesController::class, 'getAssuntos']);
            Route::post('salvar-assuntos', [SolicitacoesController::class, 'salvarAssuntos']);
            Route::post('salvar-modelos', [SolicitacoesController::class, 'salvarModelos']);
            Route::post('salvar-notif', [SolicitacoesController::class, 'saveNotificacoes']);
            Route::post('salvar-equipamento', [SolicitacoesController::class, 'addEquipamento']);
            Route::post('remover-equipamento', [SolicitacoesController::class, 'deleteEquipamento']);
            Route::post('preparar-importacao', [SolicitacoesController::class, 'prepararImportacao']);
            Route::post('importar', [SolicitacoesController::class, 'importar']);
            Route::get('liberacoes/{assunto_id}', [SolicitacoesController::class, 'getLiberacoes']);
            Route::post('salvar-liberacoes', [SolicitacoesController::class, 'salvarLiberacoes']);
            Route::get('dados-liberacao', [SolicitacoesController::class, 'getDadosLiberacao']);
            Route::get('responsaveis/{assunto_id}', [SolicitacoesController::class, 'getResponsaveis']);
            Route::post('salvar-responsaveis', [SolicitacoesController::class, 'salvarResponsaveis']);
            Route::post('duplicar-assunto', [SolicitacoesController::class, 'duplicarAssunto']);
            Route::post('toggle-ativo-assunto', [SolicitacoesController::class, 'toggleAtivoAssunto']);
            Route::post('exportar-relatorio', [SolicitacoesController::class, 'exportarRelatorio']);
            Route::get('responsaveis-adicionais/{departamento}', [SolicitacoesController::class, 'getResponsaveisAdicionais']);
            Route::post('adicionar-responsavel-adicional', [SolicitacoesController::class, 'adicionarResponsavelAdicional']);
            Route::delete('remover-responsavel-adicional', [SolicitacoesController::class, 'removerResponsavelAdicional']);
            Route::get('dados/departamentos-compras', [SolicitacoesController::class, 'getDepartamentosCompras']);
            Route::get('dados/departamentos-funcionario', [SolicitacoesController::class, 'getDepartamentosFuncionario']);
            Route::get('dados/filiais-winthor', [SolicitacoesController::class, 'getFiliaisWinthor']);
            Route::get('dados/funcoes', [SolicitacoesController::class, 'getFuncoesWinthor']);
            Route::get('dados/regionais', [SolicitacoesController::class, 'getRegionais']);
            Route::get('etapas/{assunto_id}', [SolicitacoesController::class, 'getEtapas']);
            Route::post('salvar-etapas', [SolicitacoesController::class, 'salvarEtapas']);
            Route::post('clonar-etapas', [SolicitacoesController::class, 'clonarEtapas']);
            Route::get('fluxo/{assunto_id}', [SolicitacoesController::class, 'getFluxo']);
            Route::post('salvar-fluxo', [SolicitacoesController::class, 'salvarFluxo']);
            Route::get('campos-predefinidos-fluxo', [SolicitacoesController::class, 'getCamposPredefinidos']);
            Route::get('filiais-lideranca', [SolicitacoesController::class, 'getFiliaisLideranca']);
            Route::post('filiais-lideranca', [SolicitacoesController::class, 'storeFiliaisLideranca']);
        });

        Route::prefix('nova')->group(function () {
            Route::get('', [SolicitacoesController::class, 'indexNova']);
            Route::post('criar', [SolicitacoesController::class, 'criarSolicitacao']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('', [SolicitacoesController::class, 'indexDashboard']);
            Route::post('dados', [SolicitacoesController::class, 'getDadosDashboard']);
        });

        Route::prefix('relatorios')->group(function () {
            Route::get('', [SolicitacoesController::class, 'indexRelatorios']);
            Route::post('buscar', [SolicitacoesController::class, 'buscarRelatorio']);
            Route::post('exportar', [SolicitacoesController::class, 'exportarRelatorioFiltros']);
            Route::post('exportar-fluxo', [SolicitacoesController::class, 'exportarRelatorioFluxo']);
        });

        Route::get('minhas', [SolicitacoesController::class, 'indexMinhas']);

        Route::prefix('lista')->group(function () {
            Route::get('solicitacao/{id}', [SolicitacoesController::class, 'getSolicitacao']);
            Route::get('', [SolicitacoesController::class, 'indexLista']);
            Route::get('buscar-departamentos', [SolicitacoesController::class, 'getDeptoAtivo']);
            Route::post('buscar-solicitacoes', [SolicitacoesController::class, 'getSolicitacoes']);
            Route::post('mudar-prioridade', [SolicitacoesController::class, 'mudarPrioridade']);
            Route::post('mudar-responsavel', [SolicitacoesController::class, 'mudarResponsavel']);
            Route::post('comentar', [SolicitacoesController::class, 'comentar']);
            Route::delete('comentario/{id}', [SolicitacoesController::class, 'excluirComentario']);
            Route::post('iniciar-atendimento', [SolicitacoesController::class, 'iniciarAtendimento']);
            Route::post('pausar-atendimento', [SolicitacoesController::class, 'pausarAtendimento']);
            Route::post('retorno-solicitante', [SolicitacoesController::class, 'RetornoSolicitante']);
            Route::post('resolver-atendimento', [SolicitacoesController::class, 'resolverAtendimento']);
            Route::post('finalizar-atendimento', [SolicitacoesController::class, 'finalizarAtendimento']);
            Route::post('recusar-atendimento', [SolicitacoesController::class, 'recusarAtendimento']);
            Route::post('cancelar-atendimento', [SolicitacoesController::class, 'cancelarAtendimento']);
            Route::post('alterar-departamento', [SolicitacoesController::class, 'alterarDepto']);
            Route::post('alterar-solicitante', [SolicitacoesController::class, 'alterarSolicitante']);
            Route::post('atualizar-previsao-entrega', [SolicitacoesController::class, 'atualizarPrevisaoEntrega']);
            Route::post('enviar-arquivo-dossie', [SolicitacoesController::class, 'enviarArquivoParaDossie']);
            Route::post('alterar-etapa', [SolicitacoesController::class, 'alterarEtapa']);
            Route::get('fluxo-solicitacao/{solicitacao_id}', [SolicitacoesController::class, 'getFluxoSolicitacao']);
            Route::post('avancar-fluxo', [SolicitacoesController::class, 'avancarFluxo']);
            Route::post('voltar-fluxo', [SolicitacoesController::class, 'voltarFluxo']);
            Route::post('decidir-fluxo', [SolicitacoesController::class, 'decidirFluxo']);
            Route::post('devolver-ao-fluxo', [SolicitacoesController::class, 'devolverAoFluxo']);
            Route::post('salvar-campos-fluxo', [SolicitacoesController::class, 'salvarCamposFluxo']);
        });

        Route::prefix('agendamento')->group(function () {
            Route::get('/', [SolicitacoesController::class, 'indexAgendamento']);
            Route::get('agendamentos/{id}', [SolicitacoesController::class, 'getAgendamentosByUser']);
            Route::get('buscar-agendamento/{id}', [SolicitacoesController::class, 'getAgendamentos']);
            Route::get('end-filial/{id}', [SolicitacoesController::class, 'getEnderecoFilial']);
            Route::get('buscar-anexos/{id}', [SolicitacoesController::class, 'getAnexos']);
            Route::get('buscar-solicitacoes/{id}', [SolicitacoesController::class, 'getSolAgendamentos']);
            Route::post('dados', [SolicitacoesController::class, 'getDados']);
            Route::post('buscar-por-data', [SolicitacoesController::class, 'buscaAgendamentoPorData']);
            Route::post('criar-agendamento', [SolicitacoesController::class, 'criarAgendamento']);
            Route::post('atualizar-agendamento', [SolicitacoesController::class, 'atualizarAgendamento']);
            Route::post('cancelar-agendamento', [SolicitacoesController::class, 'cancelarAgendamento']);
            Route::post('iniciar-agendamento', [SolicitacoesController::class, 'iniciarAgendamento']);
            Route::post('finalizar-agendamento', [SolicitacoesController::class, 'finalizarAgendamento']);
            Route::post('salvar-anexos', [SolicitacoesController::class, 'salvarAnexos']);
            Route::post('deletar-anexo', [SolicitacoesController::class, 'deletarAnexo']);
            Route::post('criar-lembrete', [SolicitacoesController::class, 'criarLembrete']);
            Route::post('editar-lembrete', [SolicitacoesController::class, 'editarLembrete']);
            Route::post('cancelar-lembrete', [SolicitacoesController::class, 'cancelarLembrete']);
        });

        Route::prefix('aprovacoes')->group(function () {
            Route::get('usuario', [SolicitacoesController::class, 'buscarAprovacoesUsuario']);
            Route::get('{solicitacao_id}', [SolicitacoesController::class, 'listarAprovacoes']);
            Route::post('/', [SolicitacoesController::class, 'criarAprovacao']);
            Route::post('{id}/responder', [SolicitacoesController::class, 'responderAprovacao']);
            Route::post('{id}', [SolicitacoesController::class, 'editarAprovacao']);
        });
    });
});

Route::get('/', function () {
    return redirect('/dashboard');
});

// Health check público (pra monitoramento externo / load balancer)
Route::get('/health', \App\Http\Controllers\HealthController::class)->name('health');
