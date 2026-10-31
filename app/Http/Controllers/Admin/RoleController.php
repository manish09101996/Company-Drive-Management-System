<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('roles.manage')) {
            abort(403);
        }

        $roles = Role::with(['permissions', 'users'])->get();
        $permissions = Permission::all()->groupBy('module');

        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    public function edit(Role $role): View
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('roles.manage')) {
            abort(403);
        }

        $role->load('permissions');
        $permissionsByModule = Permission::all()->groupBy('module');

        return view('admin.roles.edit', compact('role', 'permissionsByModule'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('roles.manage')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // If it's Super Admin role, ensure all permissions are maintained
        if ($role->slug === 'super-admin') {
            $role->permissions()->sync(Permission::pluck('id'));
        } else {
            $role->permissions()->sync($request->permissions ?? []);
        }

        AuditLogService::log('role.permissions_updated', 'Role', $role->id, [
            'role' => $role->name,
            'permission_count' => count($request->permissions ?? []),
        ]);

        return redirect()->route('roles.index')->with('success', "Role {$role->name} updated successfully.");
    }
}
