<?php

namespace App\Http\Controllers;

use App\Services\AuthorizationPanelService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthorizationPanelController extends Controller
{
    public function index(Request $request, AuthorizationPanelService $panel)
    {
        return Inertia::render('Approvals/AuthorizationPanel', $panel->build($request->user()));
    }
}
