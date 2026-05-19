<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            PermissionsSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@5estrelas.com.br'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $bruno = User::firstOrCreate(
            ['email' => 'bruno@bstechsolutions.com'],
            [
                'name' => 'Bruno',
                'password' => bcrypt('14021997'),
                'is_active' => true,
            ]
        );

        // Admin e Bruno recebem a permissão curinga (acesso total)
        $wildcard = Permission::where('key', '*')->first();
        if ($wildcard) {
            $admin->permissions()->syncWithoutDetaching([$wildcard->id]);
            $bruno->permissions()->syncWithoutDetaching([$wildcard->id]);
        }
    }
}
