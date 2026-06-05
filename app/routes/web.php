<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BorderoController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PayableController;
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

    // Financeiro - Contas a Pagar
    Route::prefix('financeiro/contas-pagar')->middleware('permission:financeiro.contas_pagar.visualizar')->group(function () {
        Route::get('/', [PayableController::class, 'index'])->name('payables.index');
        Route::get('/{id}', [PayableController::class, 'show'])->name('payables.show');
        Route::post('/{id}/comentarios', [PayableController::class, 'addComment'])->name('payables.comment');
        Route::post('/{id}/documentos', [PayableController::class, 'addDocument'])->name('payables.document');
        Route::delete('/{payableId}/documentos/{docId}', [PayableController::class, 'removeDocument'])->name('payables.document.remove');
        Route::post('/{id}/enviar-aprovacao', [PayableController::class, 'sendForApproval'])->name('payables.send_approval');
        Route::post('/{id}/aprovar', [PayableController::class, 'approve'])->name('payables.approve');
        Route::post('/{id}/reprovar', [PayableController::class, 'reject'])->name('payables.reject');
    });

    // Financeiro - Borderôs
    Route::prefix('financeiro/borderos')->middleware('permission:financeiro.contas_pagar.visualizar')->group(function () {
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
});

Route::get('/', function () {
    return redirect('/dashboard');
});

// Health check público (pra monitoramento externo / load balancer)
Route::get('/health', \App\Http\Controllers\HealthController::class)->name('health');
