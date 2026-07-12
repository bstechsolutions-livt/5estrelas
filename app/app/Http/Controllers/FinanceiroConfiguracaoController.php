<?php

namespace App\Http\Controllers;

use App\Support\FinanceiroConfigCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceiroConfiguracaoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = FinanceiroConfigCatalog::accessibleTo($user);

        abort_unless($items !== [], 403);

        return Inertia::render('Financeiro/Configuracao/Index', [
            'items' => $items,
        ]);
    }
}
