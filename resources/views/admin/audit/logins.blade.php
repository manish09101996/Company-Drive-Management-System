@extends('layouts.app')

@section('title', 'Security Login Events')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Security Login Events</h4>
        <p class="text-muted small mb-0">Monitor successful sign-ins, failed credential attempts, and rate-limit triggers.</p>
    </div>
    <div>
        <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary btn-sm">
            &larr; Back to Activity Audit
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 p-3">
    <form action="{{ route('audit.logins') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <input type="text" name="email" class="form-control" placeholder="Search by email..." value="{{ request('email') }}">
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Success Only</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed Only</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <button type="submit" class="btn btn-dark w-100">Filter</button>
            @if(request()->hasAny(['email', 'status']))
                <a href="{{ route('audit.logins') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-muted">
                <tr>
                    <th>Timestamp</th>
                    <th>Email Attempted</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>IP Address</th>
                    <th>Failure Reason</th>
                    <th>Browser / Device</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loginEvents as $event)
                <tr>
                    <td class="text-muted">{{ $event->created_at->format('M d, Y H:i:s') }}</td>
                    <td class="fw-semibold text-dark">{{ $event->email }}</td>
                    <td>{{ $event->user?->name ?? 'Unregistered' }}</td>
                    <td>
                        @if($event->status === 'success')
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                        @endif
                    </td>
                    <td class="font-monospace text-muted">{{ $event->ip_address }}</td>
                    <td class="text-danger">{{ $event->failure_reason ?? '-' }}</td>
                    <td class="text-muted text-truncate" style="max-width: 200px;" title="{{ $event->user_agent }}">
                        {{ $event->user_agent }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">No login events recorded.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($loginEvents->hasPages())
    <div class="card-footer bg-white border-top py-2">
        {{ $loginEvents->links() }}
    </div>
    @endif
</div>
@endsection
