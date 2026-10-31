@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-shield-lock text-primary me-2"></i>Roles & System Permissions</h4>
        <p class="text-muted small mb-0">Role-based access control (RBAC). Configure capabilities for Super Admin, Admin, Manager, and Employee.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach($roles as $role)
    <div class="col-md-6 col-xl-3">
        <div class="card h-100 p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-bold mb-0 text-dark">{{ $role->name }}</h5>
                @if($role->is_system)
                    <span class="badge bg-light text-dark border small">System Role</span>
                @endif
            </div>
            <p class="text-muted small mb-3 flex-grow-1">{{ $role->description }}</p>

            <div class="border-top pt-2 mt-auto d-flex justify-content-between align-items-center">
                <span class="small text-muted">
                    <i class="bi bi-people me-1"></i>{{ $role->users->count() }} Users
                </span>
                <span class="small fw-semibold text-primary">
                    <i class="bi bi-key me-1"></i>{{ $role->slug === 'super-admin' ? 'All (Unrestricted)' : $role->permissions->count() . ' Permissions' }}
                </span>
            </div>

            <div class="mt-3">
                @if($role->slug === 'super-admin')
                    <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                        <i class="bi bi-lock me-1"></i> Full Access Fixed
                    </button>
                @else
                    <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-pencil me-1"></i> Configure Permissions
                    </a>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

<!-- Permissions Reference Matrix -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-grid-3x3 me-2 text-primary"></i>System Permissions Catalog</h6>
    </div>
    <div class="card-body">
        <div class="row g-4">
            @foreach($permissions as $module => $perms)
            <div class="col-md-6 col-xl-4">
                <div class="card bg-light p-3 h-100 border">
                    <h6 class="fw-bold text-uppercase text-primary small mb-2"><i class="bi bi-folder-check me-1"></i>Module: {{ $module }}</h6>
                    <ul class="list-unstyled mb-0 small">
                        @foreach($perms as $p)
                        <li class="mb-2">
                            <span class="fw-semibold text-dark">{{ $p->name }}</span>
                            <div class="text-muted font-monospace" style="font-size: 0.7rem;">{{ $p->slug }}</div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
