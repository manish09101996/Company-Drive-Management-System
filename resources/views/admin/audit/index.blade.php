@extends('layouts.app')

@section('title', 'Activity Audit Trail')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i>Activity Audit Trail</h4>
        <p class="text-muted small mb-0">Immutable records of file operations, permission changes, user modifications, and administrative actions.</p>
    </div>
    <div>
        <a href="{{ route('audit.logins') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-shield-check me-1"></i> View Login Events &rarr;
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card mb-4 p-3">
    <form action="{{ route('audit.index') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="action" class="form-control" placeholder="Action (e.g. file.uploaded)..." value="{{ request('action') }}">
        </div>
        <div class="col-md-3">
            <select name="user_id" class="form-select">
                <option value="">All Users</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-md-2">
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" title="Date From">
        </div>
        <div class="col-sm-6 col-md-2">
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" title="Date To">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-dark w-100">Filter</button>
            @if(request()->hasAny(['action', 'user_id', 'date_from', 'date_to']))
                <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Audit Trail Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-muted">
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Resource</th>
                    <th>IP Address</th>
                    <th class="text-end">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="text-muted">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                    <td class="fw-semibold text-dark">{{ $log->user?->name ?? 'System' }}</td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $log->action }}</span>
                    </td>
                    <td>
                        <span class="text-secondary">{{ $log->resource_type ?? '-' }}</span>
                        @if($log->resource_id)
                            <span class="text-muted">#{{ $log->resource_id }}</span>
                        @endif
                    </td>
                    <td class="font-monospace text-muted">{{ $log->ip_address ?? '-' }}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-light border py-0 px-2" onclick="showDetailsModal('{{ $log->action }}', {{ json_encode($log->details) }})">
                            <i class="bi bi-eye"></i> Inspect
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">No audit logs match your filter criteria.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div class="card-footer bg-white border-top py-2">
        {{ $logs->links() }}
    </div>
    @endif
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="detailsModalTitle">Audit Log Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre class="bg-light p-3 rounded font-monospace small mb-0" id="detailsJsonContent" style="max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function showDetailsModal(action, details) {
        document.getElementById('detailsModalTitle').innerText = `Log Details: ${action}`;
        document.getElementById('detailsJsonContent').innerText = JSON.stringify(details, null, 2);
        new bootstrap.Modal(document.getElementById('detailsModal')).show();
    }
</script>
@endsection
