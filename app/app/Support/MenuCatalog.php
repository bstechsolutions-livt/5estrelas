<?php

namespace App\Support;

use App\Models\User;

class MenuCatalog
{
    public static function all(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'pi pi-home', 'href' => '/dashboard', 'permission' => null],
            ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'pi pi-users', 'href' => '/usuarios', 'permission' => 'usuarios.listar'],
            ['key' => 'noticias', 'label' => 'Notícias', 'icon' => 'pi pi-megaphone', 'href' => '/noticias', 'permission' => 'noticias.gerenciar'],
            ['key' => 'aparencia', 'label' => 'Aparência', 'icon' => 'pi pi-palette', 'href' => '/settings/aparencia', 'permission' => 'aparencia.editar'],
            ['key' => 'auditoria', 'label' => 'Auditoria', 'icon' => 'pi pi-history', 'href' => '/auditoria', 'permission' => 'auditoria.visualizar'],
            ['key' => 'backups', 'label' => 'Backups', 'icon' => 'pi pi-database', 'href' => '/backups', 'permission' => 'backups.gerenciar'],
            ['key' => 'departamentos', 'label' => 'Departamentos', 'icon' => 'pi pi-building', 'href' => '/departamentos', 'permission' => 'departamentos.gerenciar'],
            ['key' => 'perfil', 'label' => 'Meu perfil', 'icon' => 'pi pi-user', 'href' => '/perfil', 'permission' => null],
        ];
    }

    public static function availableTo(User $user): array
    {
        return collect(self::all())
            ->filter(fn ($i) => !$i['permission'] || $user->hasPermission($i['permission']))
            ->values()
            ->all();
    }

    public static function findByKey(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }
}
