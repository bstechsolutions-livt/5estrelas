<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
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
});

Route::get('/', function () {
    return redirect('/dashboard');
});

// Health check público (pra monitoramento externo / load balancer)
Route::get('/health', \App\Http\Controllers\HealthController::class)->name('health');
