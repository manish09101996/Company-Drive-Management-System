@extends('layouts.app')

@section('title', 'Edit User: ' . $user->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-gear text-primary me-2"></i>Edit User: {{ $user->name }}</h4>
                <p class="text-muted small mb-0">Update contact details, assigned department, roles, and account status.</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
                &larr; Back to Users
            </a>
        </div>

        <div class="card p-4">
            <form action="{{ route('users.update', $user->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">General / None</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id', $user->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" placeholder="+1 (555) 000-0000">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold d-block">Assigned Role(s)</label>
                    <div class="row g-2">
                        @foreach($roles as $role)
                        @php
                            $isAssigned = $user->roles->contains('id', $role->id);
                        @endphp
                        <div class="col-md-6">
                            <div class="card p-2 border {{ $isAssigned ? 'border-primary bg-primary-subtle' : '' }}">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}"
                                        {{ $isAssigned ? 'checked' : '' }}
                                        {{ $role->slug === 'super-admin' && (!Auth::user()->isSuperAdmin() || $user->isSuperAdmin()) ? 'disabled' : '' }}>

                                    @if($role->slug === 'super-admin' && $user->isSuperAdmin())
                                        <!-- Hidden input to guarantee super-admin role stays checked if disabled -->
                                        <input type="hidden" name="roles[]" value="{{ $role->id }}">
                                    @endif

                                    <label class="form-check-label fw-semibold small text-dark" for="role_{{ $role->id }}">
                                        {{ $role->name }}
                                        @if($role->slug === 'super-admin')
                                            <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">Protected</span>
                                        @endif
                                    </label>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $role->description }}</div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold">Account Status</label>
                    <select name="status" class="form-select w-auto" {{ $user->isSuperAdmin() ? 'disabled' : '' }}>
                        <option value="active" {{ old('status', $user->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $user->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @if($user->isSuperAdmin())
                        <input type="hidden" name="status" value="active">
                        <div class="form-text text-muted">Primary Super Admin account cannot be deactivated.</div>
                    @endif
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
