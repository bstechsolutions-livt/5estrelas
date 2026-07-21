<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->foreignId('department_assigned_by')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->timestamp('department_assigned_at')->nullable()->after('department_assigned_by');
        });

        Permission::updateOrCreate(
            ['key' => 'financeiro.contas_pagar.vincular_departamento_sync'],
            [
                'label' => 'Vincular departamento em títulos aguardando sync',
                'module' => 'financeiro',
                'description' => 'Acessar a aba Aguardando sincronização e definir manualmente o departamento de títulos importados da Senior.',
            ],
        );
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_assigned_by');
            $table->dropColumn('department_assigned_at');
        });

        Permission::where('key', 'financeiro.contas_pagar.vincular_departamento_sync')->delete();
    }
};
