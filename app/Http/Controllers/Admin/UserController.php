<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::with(['department', 'roles']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('roles.id', $request->role_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'departments', 'roles'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('users.create', compact('departments', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'department_id' => 'nullable|exists:departments,id',
            'phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        // Non-super-admins cannot assign the super-admin role
        if (!Auth::user()->isSuperAdmin()) {
            $superAdminRoleId = Role::where('slug', 'super-admin')->value('id');
            if (in_array($superAdminRoleId, $request->roles)) {
                abort(403, 'You do not have permission to grant the Super Admin role.');
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'department_id' => $request->department_id,
            'phone' => $request->phone,
            'status' => $request->status,
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync($request->roles);

        AuditLogService::log('user.created', 'User', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
        ]);

        return redirect()->route('users.index')->with('success', "User {$user->name} created successfully.");
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('users.edit', compact('user', 'departments', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'department_id' => 'nullable|exists:departments,id',
            'phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        // Prevent demoting the Super Admin or stripping Super Admin role
        if ($user->isSuperAdmin()) {
            $superAdminRoleId = Role::where('slug', 'super-admin')->value('id');
            if (!in_array($superAdminRoleId, $request->roles)) {
                return back()->withErrors(['roles' => 'The Super Admin role cannot be removed from this account.']);
            }
            if ($request->status === 'inactive') {
                return back()->withErrors(['status' => 'The primary Super Admin account cannot be deactivated.']);
            }
        }

        // Non-super-admins cannot grant the super-admin role
        if (!Auth::user()->isSuperAdmin()) {
            $superAdminRoleId = Role::where('slug', 'super-admin')->value('id');
            if (in_array($superAdminRoleId, $request->roles) && !$user->isSuperAdmin()) {
                abort(403, 'You do not have permission to grant the Super Admin role.');
            }
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'department_id' => $request->department_id,
            'phone' => $request->phone,
            'status' => $request->status,
        ]);

        $user->roles()->sync($request->roles);

        AuditLogService::log('user.updated', 'User', $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
        ]);

        return redirect()->route('users.index')->with('success', "User {$user->name} updated successfully.");
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->isSuperAdmin()) {
            return back()->withErrors(['error' => 'Super Admin cannot be deactivated.']);
        }

        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'You cannot deactivate your own account.']);
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        AuditLogService::log('user.status_toggled', 'User', $user->id, [
            'new_status' => $newStatus,
        ]);

        return back()->with('success', "User {$user->name} is now {$newStatus}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        AuditLogService::log('user.password_reset_by_admin', 'User', $user->id);

        return back()->with('success', "Password for {$user->name} reset successfully.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->isSuperAdmin()) {
            return back()->withErrors(['error' => 'The Super Admin account cannot be deleted.']);
        }

        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $userName = $user->name;
        $user->delete();

        AuditLogService::log('user.deleted', 'User', $user->id, ['name' => $userName]);

        return redirect()->route('users.index')->with('success', "User {$userName} has been deleted.");
    }
}
