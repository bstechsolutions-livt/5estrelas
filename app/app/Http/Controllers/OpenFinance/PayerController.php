<?php

namespace App\Http\Controllers\OpenFinance;

use App\Http\Controllers\Controller;
use App\Services\OpenFinance\PayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayerController extends Controller
{
    public function verify(Request $request, PayerService $payers): RedirectResponse
    {
        $data = $request->validate([
            'cpf_cnpj' => ['required', 'string', 'max:18'],
        ]);

        $result = $payers->verify($data['cpf_cnpj']);

        return back()->with($result->succeeded ? 'success' : 'error', $result->message);
    }

    public function ensure(Request $request, PayerService $payers): RedirectResponse
    {
        $data = $request->validate([
            'cpf_cnpj' => ['required', 'string', 'max:18'],
        ]);

        $result = $payers->ensurePayer($data['cpf_cnpj']);

        return back()->with($result->succeeded ? 'success' : 'error', $result->message);
    }
}
