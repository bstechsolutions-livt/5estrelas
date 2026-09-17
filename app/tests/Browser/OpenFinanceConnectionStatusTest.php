<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Homologação visual da tela Open Finance (pedaço 2.1 — pagadores).
 * Rode com Selenium/ChromeDriver: php artisan dusk --filter=OpenFinanceConnectionStatusTest
 */
class OpenFinanceConnectionStatusTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_status_renderiza_estado_da_integracao(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/open-finance')
                ->waitFor('@open-finance-page', 10)
                ->assertSee('Open Finance')
                ->assertPresent('@open-finance-configured')
                ->assertPresent('@open-finance-connected')
                ->assertPresent('@open-finance-message')
                ->assertPresent('@open-finance-payers')
                ->assertSee('Pagadores Open Finance')
                ->assertSee('Verificar novamente');
        });
    }
}
