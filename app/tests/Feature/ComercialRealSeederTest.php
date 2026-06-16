<?php

namespace Tests\Feature;

use App\Models\Comercial\Cliente;
use App\Models\Comercial\Faturamento;
use App\Models\Comercial\Proposta;
use Database\Seeders\ComercialRealSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Massa real do Comercial (propostas Nº 100–131 + clientes) extraída do protótipo.
 */
class ComercialRealSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_semeia_propostas_e_clientes_reais(): void
    {
        $this->seed(ComercialRealSeeder::class);

        $this->assertEquals(32, Proposta::count());
        $this->assertEquals(45, Cliente::count());

        // Proposta conhecida do histórico real.
        $this->assertDatabaseHas('bs_comercial_propostas', [
            'numero' => 'Nº 102',
            'cliente' => 'EMB. REINO UNIDO',
            'empresa' => 'seg-df',
            'situacao' => 'EM ANÁLISE',
            'da_cotacao' => false,
            'modelo' => 'manual',
        ]);
        $this->assertEquals(410123.17, (float) Proposta::where('numero', 'Nº 102')->first()->valor);

        // Proposta aprovada mapeia status interno 'aprovada'.
        $this->assertDatabaseHas('bs_comercial_propostas', [
            'numero' => 'Nº 101',
            'situacao' => 'APROVADO',
            'status' => 'aprovada',
        ]);

        // Cliente conhecido.
        $sesi = Cliente::where('nome', 'SESI')->first();
        $this->assertNotNull($sesi);
        $this->assertEquals('ativo', $sesi->situacao);
        $this->assertEquals(807298.09, (float) $sesi->valor_mensal);
        $this->assertEquals('DF', $sesi->uf);
    }

    public function test_semeia_faturamento_real(): void
    {
        $this->seed(ComercialRealSeeder::class);

        // 18 locais por ano (2025 + 2026).
        $this->assertEquals(18, Faturamento::where('ano', 2025)->count());
        $this->assertEquals(18, Faturamento::where('ano', 2026)->count());

        // Matriz + Embaixadas 2025: janeiro e setembro (set->setembro) reais.
        $matriz = Faturamento::where('ano', 2025)
            ->where('local_nome', '5 ESTRELAS SEGURANÇA - MATRIZ + EMBAIXADAS')->first();
        $this->assertNotNull($matriz);
        $this->assertEquals(10267008.12, (float) $matriz->jan);
        $this->assertEquals(12889327.50, (float) $matriz->setembro); // mapeado de 'set'
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(ComercialRealSeeder::class);
        $this->seed(ComercialRealSeeder::class);

        // Segunda execução não duplica (upsert por numero/nome/ano+local).
        $this->assertEquals(32, Proposta::count());
        $this->assertEquals(45, Cliente::count());
        $this->assertEquals(36, Faturamento::count());
    }
}
