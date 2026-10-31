@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-plus text-primary me-2"></i>Create New User</h4>
                <p class="text-muted small mb-0">Register a new team member and configure their roles and permissions.</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
                &larr; Back to Users
            </a>
        </div>

        <div class="card p-4">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="Jane Doe">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required placeholder="jane@company.com">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Min 8 characters">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">General / None</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="+1 (555) 000-0000">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold d-block">Assign Role(s)</label>
                    <div class="row g-2">
                        @foreach($roles as $role)
                        <div class="col-md-6">
                            <div class="card p-2 border {{ old('roles') && in_array($role->id, old('roles')) ? 'border-primary bg-primary-subtle' : '' }}">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}"
                                        {{ (is_array(old('roles')) && in_array($role->id, old('roles'))) || (!old('roles') && $role->slug === 'employee') ? 'checked' : '' }}
                                        {{ $role->slug === 'super-admin' && !Auth::user()->isSuperAdmin() ? 'disabled' : '' }}>
                                    <label class="form-check-label fw-semibold small text-dark" for="role_{{ $role->id }}">
                                        {{ $role->name }}
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
                    <select name="status" class="form-select w-auto">
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
