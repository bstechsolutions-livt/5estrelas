<?php

namespace App\Http\Controllers\OpenFinance;

use App\Http\Controllers\Controller;
use App\Services\OpenFinance\AccountConnectionService;
use App\Services\OpenFinance\TecnoSpeedClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConnectionStatusController extends Controller
{
    public function index(Request $request, AccountConnectionService $connections): Response
    {
        $client = TecnoSpeedClient::fromConfig();
        $status = $client->checkConnection();

        return Inertia::render('OpenFinance/Index', [
            'status' => $status->toFrontend(),
            'accounts' => $connections->listForFrontend($client->environment()),
            'can_manage' => $request->user()?->hasPermission('open_finance.gerenciar') ?? false,
            'p2_blocked' => ! $status->connected,
            'p2_block_message' => $status->connected
                ? null
                : 'A configuração de pagador, conta e consentimento só é liberada depois que a comunicação técnica com a TecnoSpeed estiver OK.',
        ]);
    }
}
