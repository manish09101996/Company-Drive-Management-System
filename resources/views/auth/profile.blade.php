@extends('layouts.app')

@section('title', 'My Profile & Security')

@section('content')
<div class="row g-4 justify-content-center">
    <div class="col-lg-4">
        <!-- User Profile Card -->
        <div class="card text-center p-4">
            <div class="mx-auto rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-2 mb-3 shadow" style="width: 80px; height: 80px;">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
            <p class="text-muted small mb-2">{{ $user->email }}</p>
            <div class="d-flex justify-content-center gap-1 mb-3">
                @foreach($user->roles as $role)
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $role->name }}</span>
                @endforeach
            </div>

            <hr class="my-3">

            <div class="text-start small text-muted">
                <div class="d-flex justify-content-between mb-2">
                    <span>Department:</span>
                    <strong class="text-dark">{{ $user->department?->name ?? 'General' }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Status:</span>
                    <span class="badge bg-success-subtle text-success">Active</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Phone:</span>
                    <strong class="text-dark">{{ $user->phone ?? 'Not set' }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Member Since:</span>
                    <strong class="text-dark">{{ $user->created_at->format('M d, Y') }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Edit Profile Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Profile Information</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email Address (Read-Only)</label>
                        <input type="email" class="form-control bg-light" id="email" value="{{ $user->email }}" disabled>
                        <div class="form-text">Work email can only be changed by an authorized administrator.</div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label small fw-semibold">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i>Change Password</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('profile.password') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label small fw-semibold">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label small fw-semibold">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label small fw-semibold">Confirm New Password</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning text-dark fw-semibold">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
