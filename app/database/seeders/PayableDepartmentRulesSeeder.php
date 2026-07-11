<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\PayableDepartmentRule;
use Illuminate\Database\Seeder;

/**
 * Importa regras iniciais de classificação CP a partir de config/payables.php.
 */
class PayableDepartmentRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = config('payables.department_rules', []);

        foreach ($rules as $slug => $rule) {
            $department = Department::where('slug', $slug)->where('is_active', true)->first();
            if (!$department) {
                continue;
            }

            PayableDepartmentRule::updateOrCreate(
                ['department_id' => $department->id],
                [
                    'codccu' => $rule['codccu'] ?? [],
                    'description_patterns' => PayableDepartmentRule::normalizePatterns($rule['description'] ?? []),
                ],
            );
        }

        $this->command?->info('✅ Regras de classificação CP sincronizadas (' . count($rules) . ' departamentos).');
    }
}
