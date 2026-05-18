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

        // Admin recebe a permissão curinga (acesso total)
        $wildcard = Permission::where('key', '*')->first();
        if ($wildcard) {
            $admin->permissions()->syncWithoutDetaching([$wildcard->id]);
        }
    }
}
