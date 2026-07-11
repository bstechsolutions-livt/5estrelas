<?php

namespace App\Http\Controllers;

use App\Services\FinanceiroDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceiroDashboardController extends Controller
{
    public function index(Request $request, FinanceiroDashboardService $dashboard)
    {
        $user = $request->user();

        return Inertia::render('Financeiro/Dashboard', $dashboard->build($user));
    }
}
