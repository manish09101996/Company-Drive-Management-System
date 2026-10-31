@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Company Drive Overview</h3>
        <p class="text-muted small mb-0">System metrics, storage consumption, file activity, and Takeout import status.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('drive.index') }}" class="btn btn-outline-primary d-inline-flex align-items-center gap-1">
            <i class="bi bi-folder-plus"></i> New Folder
        </a>
        <a href="{{ route('drive.index') }}" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-cloud-arrow-up"></i> Upload Files
        </a>
        <a href="{{ route('imports.index') }}" class="btn btn-dark d-inline-flex align-items-center gap-1">
            <i class="bi bi-box-arrow-in-down"></i> Import Takeout
        </a>
    </div>
</div>

<!-- Storage Warning Alert if active -->
@if($importWarning)
<div class="alert alert-warning alert-dismissible fade show d-flex align-items-center shadow-sm mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
    <div>
        <div class="fw-bold">Storage / Import Notice</div>
        <div class="small">{{ $importWarning->error_summary }}</div>
    </div>
    <a href="{{ route('imports.show', $importWarning->uuid) }}" class="btn btn-sm btn-outline-dark ms-auto me-2">View Job</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Metric Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">TOTAL FILES</span>
                <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-file-earmark-text-fill fs-5"></i>
                </div>
            </div>
            <h2 class="fw-bold mb-1">{{ number_format($totalFiles) }}</h2>
            <div class="text-muted small">
                <span class="text-success fw-semibold"><i class="bi bi-folder-fill me-1"></i>{{ number_format($totalFolders) }}</span> folders created
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">STORAGE USED</span>
                <div class="rounded-circle bg-info-subtle text-info p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-hdd-fill fs-5"></i>
                </div>
            </div>
            <h2 class="fw-bold mb-1">{{ $formattedStorageUsed }} <span class="fs-6 fw-normal text-muted">/ {{ $diskStats['total_formatted'] }}</span></h2>
            <div class="text-muted small">
                Server Free: <span class="fw-semibold text-dark">{{ $diskStats['free_formatted'] }}</span>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">USERS & ROLES</span>
                <div class="rounded-circle bg-success-subtle text-success p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-people-fill fs-5"></i>
                </div>
            </div>
            <h2 class="fw-bold mb-1">{{ number_format($totalUsers) }}</h2>
            <div class="text-muted small">
                <span class="badge bg-success-subtle text-success">{{ $activeUsers }} Active</span>
                @if($inactiveUsers > 0)
                <span class="badge bg-secondary-subtle text-secondary">{{ $inactiveUsers }} Inactive</span>
                @endif
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">TAKEOUT IMPORTS</span>
                <div class="rounded-circle bg-warning-subtle text-warning p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-box-arrow-in-down fs-5"></i>
                </div>
            </div>
            <h2 class="fw-bold mb-1">{{ $recentImports->count() }}</h2>
            <div class="text-muted small">
                @if($activeImports->count() > 0)
                <span class="badge bg-primary text-white">{{ $activeImports->count() }} In Progress</span>
                @else
                <span class="badge bg-secondary-subtle text-secondary">No active imports</span>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Storage Breakdown Bar -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-pie-chart-fill me-2 text-primary"></i>
            <span>Storage Allocation & Categories</span>
        </div>
        <span class="text-muted small">Plan: <strong>{{ $diskStats['total_formatted'] }}</strong> &bull; Free: <strong class="text-success">{{ $diskStats['free_formatted'] }}</strong></span>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
            <span>Overall Plan Usage: <strong>{{ $formattedStorageUsed }}</strong> of <strong>{{ $diskStats['total_formatted'] }}</strong></span>
            <span><strong>{{ $diskStats['used_percentage'] }}%</strong> consumed</span>
        </div>
        <div class="progress mb-3" style="height: 8px;">
            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ max(1, $diskStats['used_percentage']) }}%" title="{{ $diskStats['used_percentage'] }}% used"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
            <span>File Type Distribution ({{ $formattedStorageUsed }})</span>
        </div>
        <div class="progress mb-3" style="height: 12px;">
            @php
                $docPercent = $totalFileBytes > 0 ? round(($fileTypes['Documents'] / $totalFileBytes) * 100, 1) : 0;
                $imgPercent = $totalFileBytes > 0 ? round(($fileTypes['Images'] / $totalFileBytes) * 100, 1) : 0;
                $medPercent = $totalFileBytes > 0 ? round(($fileTypes['Media'] / $totalFileBytes) * 100, 1) : 0;
                $arcPercent = $totalFileBytes > 0 ? round(($fileTypes['Archives'] / $totalFileBytes) * 100, 1) : 0;
                $othPercent = $totalFileBytes > 0 ? round(($fileTypes['Other'] / $totalFileBytes) * 100, 1) : 0;
            @endphp
            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $docPercent }}%" title="Documents: {{ $docPercent }}%"></div>
            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $imgPercent }}%" title="Images: {{ $imgPercent }}%"></div>
            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $medPercent }}%" title="Media: {{ $medPercent }}%"></div>
            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $arcPercent }}%" title="Archives: {{ $arcPercent }}%"></div>
            <div class="progress-bar bg-secondary" role="progressbar" style="width: {{ $othPercent }}%" title="Other: {{ $othPercent }}%"></div>
        </div>

        <div class="row g-2 text-center small text-muted">
            <div class="col">
                <span class="d-inline-block rounded-circle bg-primary me-1" style="width: 8px; height: 8px;"></span>
                <span>Docs ({{ $docPercent }}%)</span>
            </div>
            <div class="col">
                <span class="d-inline-block rounded-circle bg-success me-1" style="width: 8px; height: 8px;"></span>
                <span>Images ({{ $imgPercent }}%)</span>
            </div>
            <div class="col">
                <span class="d-inline-block rounded-circle bg-danger me-1" style="width: 8px; height: 8px;"></span>
                <span>Media ({{ $medPercent }}%)</span>
            </div>
            <div class="col">
                <span class="d-inline-block rounded-circle bg-warning me-1" style="width: 8px; height: 8px;"></span>
                <span>Archives ({{ $arcPercent }}%)</span>
            </div>
            <div class="col">
                <span class="d-inline-block rounded-circle bg-secondary me-1" style="width: 8px; height: 8px;"></span>
                <span>Other ({{ $othPercent }}%)</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Files Table -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-clock-history me-2 text-primary"></i>
                    <span>Recently Uploaded Files</span>
                </div>
                <a href="{{ route('drive.index') }}" class="btn btn-sm btn-link text-decoration-none">View All Files &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Name</th>
                            <th>Folder</th>
                            <th>Size</th>
                            <th>Uploader</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentFiles as $file)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi {{ $file->icon_class }} fs-5"></i>
                                    <div class="text-truncate" style="max-width: 180px;">
                                        <div class="fw-semibold text-dark small text-truncate" title="{{ $file->name }}">{{ $file->name }}</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ $file->created_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="small text-muted">
                                {{ $file->folder?->name ?? 'Root Drive' }}
                            </td>
                            <td class="small fw-semibold text-dark">{{ $file->formatted_size }}</td>
                            <td class="small text-muted">{{ $file->uploader?->name ?? 'System' }}</td>
                            <td class="text-end">
                                <a href="{{ route('files.download', $file->id) }}" class="btn btn-sm btn-light border" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted small">
                                <i class="bi bi-cloud-arrow-up fs-2 d-block mb-1 text-secondary"></i>
                                No files uploaded yet. <a href="{{ route('drive.index') }}">Upload your first file</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Audit Activity Feed -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-journal-text me-2 text-primary"></i>
                    <span>Recent Activity Trail</span>
                </div>
                @if(Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('audit.view'))
                <a href="{{ route('audit.index') }}" class="btn btn-sm btn-link text-decoration-none">Full Audit &rarr;</a>
                @endif
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentLogs as $log)
                    <li class="list-group-item px-3 py-2 small">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge bg-light text-dark border">{{ $log->action }}</span>
                            <span class="text-muted" style="font-size: 0.7rem;">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-dark fw-medium">{{ $log->user?->name ?? 'System' }}</div>
                        <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                            @if($log->details)
                                {{ json_encode($log->details) }}
                            @else
                                {{ $log->resource_type }} #{{ $log->resource_id }}
                            @endif
                        </div>
                    </li>
                    @empty
                    <li class="list-group-item text-center py-4 text-muted small">
                        No activity recorded yet.
                    </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
