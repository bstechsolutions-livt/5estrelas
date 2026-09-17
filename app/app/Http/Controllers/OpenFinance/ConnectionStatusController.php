<?php

namespace App\Http\Controllers\OpenFinance;

use App\Http\Controllers\Controller;
use App\Services\OpenFinance\PayerService;
use App\Services\OpenFinance\TecnoSpeedClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConnectionStatusController extends Controller
{
    public function index(Request $request, PayerService $payers): Response
    {
        $status = TecnoSpeedClient::fromConfig()->checkConnection();

        return Inertia::render('OpenFinance/Index', [
            'status' => $status->toFrontend(),
            'payers' => $payers->listForFrontend(),
            'can_manage' => $request->user()?->hasPermission('open_finance.gerenciar') ?? false,
        ]);
    }
}
