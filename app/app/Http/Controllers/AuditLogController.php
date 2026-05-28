<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $query = AuditLog::query()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from . ' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                    ->orWhere('user_name', 'like', "%{$s}%")
                    ->orWhere('event', 'like', "%{$s}%");
            });
        }

        $logs = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($logs);
        }

        // Listas para filtros (distinct)
        $modules = AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $events = AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event');
        $users = User::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => [
                'module' => $request->module,
                'event' => $request->event,
                'user_id' => $request->user_id,
                'from' => $request->from,
                'to' => $request->to,
                'search' => $request->search,
                'per_page' => $perPage,
            ],
            'options' => [
                'modules' => $modules,
                'events' => $events,
                'users' => $users,
            ],
        ]);
    }
}
