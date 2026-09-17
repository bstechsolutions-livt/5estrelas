<?php

namespace App\Http\Controllers\OpenFinance;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\OpenFinanceAccountConnection;
use App\Services\OpenFinance\AccountConnectionService;
use App\Services\OpenFinance\TecnoSpeedClient;
use App\Services\OpenFinance\TecnoSpeedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountConnectionController extends Controller
{
    public function preview(Request $request, BankAccount $bankAccount, AccountConnectionService $connections): JsonResponse
    {
        $environment = TecnoSpeedClient::fromConfig()->environment();

        return response()->json($connections->preview($bankAccount, $environment));
    }

    public function store(Request $request, AccountConnectionService $connections): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:250'],
            'cpf_cnpj' => ['required', 'string', 'max:18'],
            'email' => ['nullable', 'email', 'max:250'],
            'zipcode' => ['required', 'string', 'max:10'],
            'street' => ['nullable', 'string', 'max:250'],
            'address_number' => ['nullable', 'string', 'max:10'],
            'address_complement' => ['nullable', 'string', 'max:250'],
            'neighborhood' => ['required', 'string', 'max:250'],
            'city' => ['required', 'string', 'max:250'],
            'state' => ['required', 'string', 'size:2'],
        ]);

        $account = BankAccount::query()->findOrFail($data['bank_account_id']);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        $environment = TecnoSpeedClient::fromConfig()->environment();

        try {
            $connection = $connections->prepare($account, $branch, $data, $environment);
        } catch (TecnoSpeedException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = match ($connection->status) {
            OpenFinanceAccountConnection::STATUS_CONNECTED => 'Conexão Open Finance já estava confirmada.',
            OpenFinanceAccountConnection::STATUS_AWAITING_AUTHORIZATION => 'Conta preparada para autorização.',
            OpenFinanceAccountConnection::STATUS_ACCOUNT_READY => 'Conta cadastrada na TecnoSpeed. Autorize o acesso no banco quando o link estiver disponível.',
            default => 'Configuração Open Finance atualizada.',
        };

        return back()->with('success', $message);
    }

    public function authorizationLink(
        OpenFinanceAccountConnection $connection,
        AccountConnectionService $connections,
    ): JsonResponse {
        $this->assertEnvironment($connection);

        try {
            $url = $connections->authorizationUrl($connection);
        } catch (TecnoSpeedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['url' => $url]);
    }

    public function verify(
        OpenFinanceAccountConnection $connection,
        AccountConnectionService $connections,
    ): RedirectResponse {
        $this->assertEnvironment($connection);

        try {
            $updated = $connections->verify($connection);
        } catch (TecnoSpeedException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($updated->isConnected()) {
            return back()->with('success', 'Conexão confirmada. O conector Open Finance está ativo nesta conta.');
        }

        return back()->with(
            'warning',
            'A autorização ainda não foi concluída ou ainda está sendo processada pela instituição.',
        );
    }

    private function assertEnvironment(OpenFinanceAccountConnection $connection): void
    {
        $environment = TecnoSpeedClient::fromConfig()->environment();
        abort_unless($connection->environment === $environment, 404);
    }
}
