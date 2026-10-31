<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('audit.view')) {
            abort(403);
        }

        $query = AuditLog::with('user');

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.audit.index', compact('logs', 'users'));
    }

    public function loginEvents(Request $request): View
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('audit.view')) {
            abort(403);
        }

        $query = LoginEvent::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', "%{$request->email}%");
        }

        $loginEvents = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        return view('admin.audit.logins', compact('loginEvents'));
    }
}
