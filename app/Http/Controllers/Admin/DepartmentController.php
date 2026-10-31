<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('settings.manage')) {
            abort(403);
        }

        $departments = Department::withCount(['users', 'folders', 'files'])->get();
        return view('admin.departments.index', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('settings.manage')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:departments,code',
            'description' => 'nullable|string|max:500',
        ]);

        $dept = Department::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'is_active' => true,
        ]);

        AuditLogService::log('department.created', 'Department', $dept->id, ['name' => $dept->name]);

        return back()->with('success', "Department {$dept->name} created successfully.");
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('settings.manage')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => "required|string|max:20|unique:departments,code,{$department->id}",
            'description' => 'nullable|string|max:500',
            'is_active' => 'required|boolean',
        ]);

        $department->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'is_active' => (bool) $request->is_active,
        ]);

        AuditLogService::log('department.updated', 'Department', $department->id, ['name' => $department->name]);

        return back()->with('success', "Department {$department->name} updated.");
    }
}
