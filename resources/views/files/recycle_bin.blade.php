@extends('layouts.app')

@section('title', 'Recycle Bin')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-trash3 text-danger me-2"></i>Recycle Bin</h4>
        <p class="text-muted small mb-0">Items are stored for 30 days before permanent deletion. Restore or permanently purge items.</p>
    </div>

    @if($items->count() > 0)
    <form action="{{ route('recycle-bin.empty') }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently empty the Recycle Bin? All deleted physical files will be permanently erased from storage.');">
        @csrf
        <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-x-octagon"></i> Empty Recycle Bin
        </button>
    </form>
    @endif
</div>

<!-- Stats Card -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card p-3">
            <div class="text-muted small fw-semibold">DELETED ITEMS</div>
            <div class="fs-4 fw-bold text-dark">{{ number_format($totalItemsCount) }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card p-3">
            <div class="text-muted small fw-semibold">STORAGE IN BIN</div>
            <div class="fs-4 fw-bold text-dark">{{ $totalBytesFormatted }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-6">
        <div class="card p-3 bg-light">
            <div class="text-muted small fw-semibold">RETENTION POLICY</div>
            <div class="small text-muted mt-1">
                Automated scheduler purges items older than 30 days. You can also run <code>php artisan drive:purge-recycle-bin</code> via cron.
            </div>
        </div>
    </div>
</div>

<!-- Deleted Items Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Original Name</th>
                    <th>Item Type</th>
                    <th>Size</th>
                    <th>Deleted By</th>
                    <th>Deleted At</th>
                    <th>Expires In</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td class="text-center">
                        @if($item->item_type === 'folder')
                            <i class="bi bi-folder-fill fs-5 text-secondary"></i>
                        @else
                            <i class="bi bi-file-earmark fs-5 text-secondary"></i>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $item->original_name }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border text-capitalize">{{ $item->item_type }}</span>
                    </td>
                    <td class="small fw-semibold text-dark">{{ $item->formatted_size }}</td>
                    <td class="small text-muted">{{ $item->deleter?->name ?? 'User' }}</td>
                    <td class="small text-muted">{{ $item->deleted_at->format('M d, Y H:i') }}</td>
                    <td class="small">
                        @php
                            $daysLeft = max(0, now()->diffInDays($item->expires_at, false));
                        @endphp
                        @if($daysLeft > 5)
                            <span class="badge bg-secondary-subtle text-secondary">{{ $daysLeft }} days left</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger">{{ $daysLeft }} days left</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <form action="{{ route('recycle-bin.restore', $item->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-light border text-success" title="Restore Item">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                </button>
                            </form>
                            <form action="{{ route('recycle-bin.force', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete \'{{ $item->original_name }}\'? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light border text-danger" title="Permanently Delete">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted small">
                        <i class="bi bi-trash3 display-4 text-secondary d-block mb-2"></i>
                        Recycle Bin is currently empty.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($items->hasPages())
    <div class="card-footer bg-white border-top py-2">
        {{ $items->links() }}
    </div>
    @endif
</div>
@endsection
