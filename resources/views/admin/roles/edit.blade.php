@extends('layouts.app')

@section('title', 'Edit Role: ' . $role->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Configure Role: {{ $role->name }}</h4>
                <p class="text-muted small mb-0">Select the capabilities and operational permissions granted to users with this role.</p>
            </div>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm">
                &larr; Back to Roles
            </a>
        </div>

        <form action="{{ route('roles.update', $role->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card p-4 mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Role Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $role->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description', $role->description) }}">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-key me-2 text-primary"></i>Permissions Checklist</h6>
                    <span class="badge bg-light text-dark border">Grouped by Subsystem</span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        @foreach($permissionsByModule as $module => $modulePerms)
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <h6 class="fw-bold text-uppercase text-primary small mb-3 border-bottom pb-2">
                                    <i class="bi bi-check2-square me-1"></i>{{ ucfirst($module) }} Permissions
                                </h6>
                                <div class="d-flex flex-column gap-2">
                                    @foreach($modulePerms as $perm)
                                    @php
                                        $hasPerm = $role->permissions->contains('id', $perm->id);
                                    @endphp
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}"
                                            {{ $hasPerm ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold small text-dark" for="perm_{{ $perm->id }}">
                                            {{ $perm->name }}
                                        </label>
                                        <div class="text-muted" style="font-size: 0.72rem;">{{ $perm->description }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">Save Permissions</button>
            </div>
        </form>
    </div>
</div>
@endsection
