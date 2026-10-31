@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>User & Role Management</h4>
        <p class="text-muted small mb-0">Configure company personnel, assign access roles, manage credentials and permissions.</p>
    </div>
    <div>
        <a href="{{ route('users.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-person-plus"></i> New User
        </a>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card mb-4 p-3">
    <form action="{{ route('users.index') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, email, phone..." value="{{ request('search') }}">
            </div>
        </div>
        <div class="col-sm-4 col-md-3">
            <select name="department_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4 col-md-2">
            <select name="role_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4 col-md-2">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="col-sm-4 col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-dark w-100">Filter</button>
            @if(request()->hasAny(['search', 'department_id', 'role_id', 'status']))
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>User</th>
                    <th>Department</th>
                    <th>Role(s)</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-semibold text-dark">{{ $user->name }}</div>
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="small text-muted">{{ $user->department?->name ?? 'General' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($user->roles as $role)
                                @php
                                    $roleBadge = match($role->slug) {
                                        'super-admin' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                        'admin' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'manager' => 'bg-info-subtle text-info border border-info-subtle',
                                        default => 'bg-light text-secondary border'
                                    };
                                @endphp
                                <span class="badge {{ $roleBadge }} small">{{ $role->name }}</span>
                            @endforeach
                        </div>
                    </td>
                    <td class="small text-muted">{{ $user->phone ?? '-' }}</td>
                    <td>
                        @if($user->status === 'active')
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $user->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <a class="dropdown-item" href="{{ route('users.edit', $user->id) }}">
                                        <i class="bi bi-pencil me-2 text-primary"></i>Edit User
                                    </a>
                                </li>
                                <li>
                                    <button class="dropdown-item" onclick="openResetPasswordModal({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                        <i class="bi bi-key me-2 text-warning"></i>Reset Password
                                    </button>
                                </li>

                                @if(!$user->isSuperAdmin() && $user->id !== Auth::id())
                                <li>
                                    <form action="{{ route('users.toggle-status', $user->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            @if($user->status === 'active')
                                                <i class="bi bi-person-x me-2 text-secondary"></i>Deactivate User
                                            @else
                                                <i class="bi bi-person-check me-2 text-success"></i>Reactivate User
                                            @endif
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Delete user \'{{ $user->name }}\'?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash3 me-2"></i>Delete User
                                        </button>
                                    </form>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted small">
                        No users match the search filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
    <div class="card-footer bg-white border-top py-2">
        {{ $users->links() }}
    </div>
    @endif
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="resetPasswordForm" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-key text-warning me-2"></i>Reset Password: <span id="resetUserName"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">New Temporary Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Minimum 8 characters">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark fw-semibold">Reset Password</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openResetPasswordModal(userId, userName) {
        document.getElementById('resetUserName').innerText = userName;
        document.getElementById('resetPasswordForm').action = appUrl('/users/' + userId + '/reset-password');
        new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
    }
</script>
@endsection
