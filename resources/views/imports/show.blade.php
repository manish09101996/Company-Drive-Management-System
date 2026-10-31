@extends('layouts.app')

@section('title', 'ZIP Extractor: ' . $job->title)

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('imports.index') }}" class="text-decoration-none">Archive & Takeout Center</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $job->title }}</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-file-earmark-zip text-primary me-2"></i>{{ $job->title }}
        </h4>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @if(in_array($job->status, ['completed', 'partially_completed']))
        <a href="{{ $job->destinationFolder ? route('drive.index', $job->destinationFolder->uuid) : route('drive.index') }}" class="btn btn-success fw-semibold shadow-sm">
            <i class="bi bi-folder2-open me-1"></i> View Extracted Files in Drive &rarr;
        </a>
        @endif

        @if($job->status === 'pending')
        <form action="{{ route('imports.inspect', $job->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i> Inspect & Verify Archive
            </button>
        </form>
        @endif

        @if(in_array($job->status, ['processing', 'partially_completed', 'failed']) && $job->failed_entries > 0)
        <form action="{{ route('imports.retry', $job->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-warning text-dark fw-semibold">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Retry Failed Items ({{ $job->failed_entries }})
            </button>
        </form>
        @endif

        @if($job->status === 'processing')
        <form action="{{ route('imports.cancel', $job->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-slash-circle me-1"></i> Cancel Job
            </button>
        </form>
        @endif
    </div>
</div>

<!-- Completion Banner -->
@if($job->status === 'completed')
<div class="alert alert-success d-flex align-items-center justify-content-between mb-4 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
        <div>
            <h6 class="fw-bold mb-0">Extraction Completed Successfully!</h6>
            <div class="small text-secondary">
                All <strong>{{ number_format($job->imported_entries) }}</strong> entries extracted and verified with SHA-256 checksums into 
                <strong>{{ $job->destinationFolder?->name ?? 'Root Drive' }}</strong>.
                @if(!empty($job->settings['source_deleted']))
                    <span class="text-danger fw-semibold ms-1">(Source archive file removed to reclaim storage).</span>
                @endif
            </div>
        </div>
    </div>
    <a href="{{ $job->destinationFolder ? route('drive.index', $job->destinationFolder->uuid) : route('drive.index') }}" class="btn btn-sm btn-success fw-semibold">
        Open in Drive &rarr;
    </a>
</div>
@endif

<!-- Storage / Integrity Warning Banner -->
@if($job->error_summary)
<div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-shield-exclamation fs-4 me-3 text-warning"></i>
    <div>
        <strong>Archive Notice:</strong> {{ $job->error_summary }}
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<!-- Job Summary & Batch Controller -->
<div class="row g-4 mb-4">
    <!-- Archive Info & Security Audits -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-check me-2 text-primary"></i>Archive Security & Specifications</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small mb-3">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Archive Filename:</span>
                        <strong class="text-dark font-monospace text-truncate" style="max-width: 220px;" title="{{ $job->archive_filename }}">{{ $job->archive_filename }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Compressed Size:</span>
                        <strong class="text-dark">{{ $job->formatted_size }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Estimated Extracted Size:</span>
                        <strong class="text-primary fw-bold">{{ $job->estimated_extracted_size }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Total Archive Entries:</span>
                        <strong class="text-dark">{{ number_format($job->total_entries) }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Hierarchy Breakdown:</span>
                        <strong class="text-dark">{{ $job->total_folders }} folders / {{ $job->total_files }} files</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Destination Location:</span>
                        <strong class="text-dark">{{ $job->destinationFolder?->name ?? 'Root Drive' }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Extraction Status:</span>
                        @php
                            $badgeClass = match($job->status) {
                                'pending' => 'bg-secondary',
                                'manifest_ready' => 'bg-info',
                                'processing' => 'bg-warning text-dark',
                                'completed' => 'bg-success',
                                'partially_completed' => 'bg-warning text-dark',
                                'failed' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }} text-uppercase" id="jobStatusBadge">{{ str_replace('_', ' ', $job->status) }}</span>
                    </li>
                </ul>

                <h6 class="fw-semibold small text-muted mb-2">Automated Security Audits:</h6>
                <div class="row g-2 small">
                    <div class="col-12 text-success">
                        <i class="bi bi-check-circle-fill me-1"></i> Zip Slip & Path Traversal Blocked (<code>../</code> stripped)
                    </div>
                    <div class="col-12 text-success">
                        <i class="bi bi-check-circle-fill me-1"></i> Decompression Ratio Verified (&lt; 100:1 safety ratio)
                    </div>
                    <div class="col-12 text-success">
                        <i class="bi bi-check-circle-fill me-1"></i> Absolute Paths & Symlinks Rejected
                    </div>
                    <div class="col-12 text-success">
                        <i class="bi bi-check-circle-fill me-1"></i> Private Storage Hash Mapping (SHA-256)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Batch Processing Controller -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-play-circle me-2 text-primary"></i>Extraction Runner (Controlled Batches)</h6>
                <span class="badge bg-light text-dark border">Shared Hosting Safe (50/batch)</span>
            </div>
            <div class="card-body">
                <!-- If manifest is ready, show configuration & start options -->
                @if(in_array($job->status, ['manifest_ready', 'pending']))
                <form action="{{ route('imports.start', $job->id) }}" method="POST" class="mb-4 p-3 bg-light rounded border">
                    @csrf
                    <h6 class="fw-bold mb-3"><i class="bi bi-sliders me-1 text-primary"></i>Configure Extraction Options</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Destination Folder in Drive</label>
                            <select name="destination_folder_id" class="form-select form-select-sm">
                                <option value="">Root Storage (Top Level)</option>
                                @foreach($folders as $folder)
                                <option value="{{ $folder->id }}" {{ $job->destination_folder_id == $folder->id ? 'selected' : '' }}>
                                    📁 {{ $folder->name }}
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text" style="font-size: 0.72rem;">Folders in ZIP will be recreated inside this destination.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Default Access Role</label>
                            <select name="default_access_role" class="form-select form-select-sm">
                                @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ $role->slug === 'employee' ? 'selected' : '' }}>{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" style="font-size: 0.72rem;">Assigned default view permissions.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Duplicate File Conflict Action</label>
                            <select name="duplicate_action" class="form-select form-select-sm">
                                <option value="skip" {{ ($job->settings['duplicate_action'] ?? '') === 'skip' ? 'selected' : '' }}>Skip Duplicate Files (Recommended)</option>
                                <option value="rename" {{ ($job->settings['duplicate_action'] ?? '') === 'rename' ? 'selected' : '' }}>Rename (Keep Both with Numbered Suffix)</option>
                                <option value="overwrite" {{ ($job->settings['duplicate_action'] ?? '') === 'overwrite' ? 'selected' : '' }}>Overwrite (Store as New File Version)</option>
                            </select>
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch pt-3">
                                <input class="form-check-input" type="checkbox" name="delete_source_zip" id="deleteSourceZip" value="1" {{ !empty($job->settings['delete_source_zip']) ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold text-danger" for="deleteSourceZip">
                                    Delete source ZIP file after extraction
                                </label>
                                <div class="text-muted" style="font-size: 0.7rem;">Saves disk space once all files are verified.</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3 py-2 fw-semibold w-100 shadow-sm">
                        <i class="bi bi-play-fill me-1 fs-5"></i> Start Archive Extraction
                    </button>
                </form>
                @endif

                <!-- Extraction Progress Bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span class="fw-semibold">Decompression & Verification Progress</span>
                        <span id="runnerPercent" class="fw-bold text-primary fs-6">{{ $job->progress_percentage }}%</span>
                    </div>
                    <div class="progress" style="height: 14px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated {{ $job->status === 'completed' ? 'bg-success' : 'bg-primary' }}" id="runnerProgressBar" role="progressbar" style="width: {{ $job->progress_percentage }}%"></div>
                    </div>
                </div>

                <!-- 5-Metric Counter Grid -->
                <div class="row g-2 text-center small mb-3">
                    <div class="col">
                        <div class="card p-2 bg-light border-0">
                            <div class="text-muted" style="font-size: 0.68rem; font-weight: 700;">EXTRACTED</div>
                            <div class="fw-bold text-success fs-5" id="runnerImported">{{ $job->imported_entries }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-2 bg-light border-0">
                            <div class="text-muted" style="font-size: 0.68rem; font-weight: 700;">SKIPPED</div>
                            <div class="fw-bold text-warning fs-5" id="runnerSkipped">{{ $job->skipped_entries }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-2 bg-light border-0">
                            <div class="text-muted" style="font-size: 0.68rem; font-weight: 700;">FAILED</div>
                            <div class="fw-bold text-danger fs-5" id="runnerFailed">{{ $job->failed_entries }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-2 bg-light border-0">
                            <div class="text-muted" style="font-size: 0.68rem; font-weight: 700;">REMAINING</div>
                            <div class="fw-bold text-dark fs-5" id="runnerRemaining">{{ max(0, $job->total_entries - ($job->imported_entries + $job->failed_entries + $job->skipped_entries)) }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-2 bg-light border-0">
                            <div class="text-muted" style="font-size: 0.68rem; font-weight: 700;">TOTAL</div>
                            <div class="fw-bold text-primary fs-5" id="runnerTotal">{{ $job->total_entries }}</div>
                        </div>
                    </div>
                </div>

                <!-- Runner Controls & Live Log -->
                @if(in_array($job->status, ['processing', 'partially_completed']))
                <div class="d-flex gap-2 mb-3">
                    <button type="button" id="startBatchRunnerBtn" class="btn btn-primary flex-grow-1 fw-semibold py-2" onclick="startBatchProcessing()">
                        <i class="bi bi-play-circle me-1"></i> Run Extraction Runner (In-Browser)
                    </button>
                    <button type="button" id="pauseBatchRunnerBtn" class="btn btn-outline-secondary d-none py-2" onclick="pauseBatchProcessing()">
                        <i class="bi bi-pause-circle me-1"></i> Pause Runner
                    </button>
                </div>
                @endif

                <div class="bg-dark text-light p-2 rounded font-monospace small" style="height: 130px; overflow-y: auto;" id="batchLogConsole">
                    <div>[System] ZIP Extractor ready. Status: {{ $job->status }}</div>
                    @if($job->status === 'completed')
                    <div class="text-success">[System] Extraction complete. All archive files extracted and verified.</div>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                    <span>CLI Alternative: <code>php artisan drive:import-process {{ $job->uuid }}</code></span>
                    <span id="runnerBatchSpeed" class="fw-semibold text-secondary">50 entries / request</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Manifest Entries & Selection Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-folder-tree me-2 text-primary"></i>Archive Manifest & Selective Extraction</h6>
                <div class="small text-muted">Select specific items to extract or deselect items to skip.</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group input-group-sm" style="width: 240px;">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="manifestSearchInput" class="form-control" placeholder="Search archive entries..." onkeyup="filterManifestTable()">
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkSelect(true)" title="Select all items for extraction">
                    <i class="bi bi-check-all me-1"></i> Select All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkSelect(false)" title="Deselect all items (skip them)">
                    <i class="bi bi-dash-square me-1"></i> Deselect All
                </button>
            </div>
        </div>
    </div>
    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0 small" id="manifestTable">
            <thead class="table-light text-muted sticky-top">
                <tr>
                    <th style="width: 40px;" class="text-center">
                        <input type="checkbox" id="masterSelectCb" class="form-check-input" checked onchange="toggleMasterCheckbox(this.checked)" title="Toggle all visible entries">
                    </th>
                    <th>Name</th>
                    <th>Path in Archive</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Notes / Errors</th>
                </tr>
            </thead>
            <tbody id="manifestTableBody">
                @forelse($itemsPreview as $item)
                <tr class="manifest-row" data-id="{{ $item->id }}" data-name="{{ strtolower($item->entry_name) }}" data-path="{{ strtolower($item->entry_path) }}">
                    <td class="text-center">
                        <input type="checkbox" 
                               class="form-check-input item-select-cb" 
                               data-id="{{ $item->id }}" 
                               {{ $item->status !== 'skipped' ? 'checked' : '' }} 
                               {{ $item->status === 'completed' ? 'disabled' : '' }}
                               onchange="toggleItemSelection({{ $item->id }}, this.checked)">
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($item->entry_type === 'folder')
                                <i class="bi bi-folder-fill text-primary fs-5"></i>
                            @else
                                <i class="bi bi-file-earmark-text text-secondary fs-5"></i>
                            @endif
                            <span class="fw-semibold text-dark">{{ $item->entry_name }}</span>
                        </div>
                    </td>
                    <td class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $item->entry_path }}</td>
                    <td>
                        <span class="badge {{ $item->entry_type === 'folder' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-dark border' }} text-capitalize">
                            {{ $item->entry_type }}
                        </span>
                    </td>
                    <td>{{ $item->formatted_size }}</td>
                    <td>
                        @php
                            $itemBadge = match($item->status) {
                                'pending' => 'bg-secondary',
                                'processing' => 'bg-warning text-dark',
                                'completed' => 'bg-success',
                                'failed' => 'bg-danger',
                                'skipped' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                default => 'bg-light text-dark'
                            };
                        @endphp
                        <span class="badge {{ $itemBadge }} item-status-badge" id="itemStatus-{{ $item->id }}">{{ ucfirst($item->status) }}</span>
                    </td>
                    <td class="text-danger small" id="itemError-{{ $item->id }}">
                        {{ $item->error_message ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-search display-6 d-block mb-2 text-secondary"></i>
                        No manifest entries generated yet. Click "Inspect & Verify Archive" above to scan and prepare this archive.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between align-items-center">
        <span>Showing {{ min(count($itemsPreview), $job->total_entries) }} of {{ number_format($job->total_entries) }} entries</span>
        <span class="fst-italic">Hierarchical folders and subfolders are automatically recreated upon extraction.</span>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let runnerActive = false;
    const jobId = {{ $job->id }};

    function logMessage(msg) {
        const consoleEl = document.getElementById('batchLogConsole');
        if (!consoleEl) return;
        const line = document.createElement('div');
        line.innerText = `[${new Date().toLocaleTimeString()}] ${msg}`;
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function startBatchProcessing() {
        runnerActive = true;
        const startBtn = document.getElementById('startBatchRunnerBtn');
        const pauseBtn = document.getElementById('pauseBatchRunnerBtn');
        if (startBtn) startBtn.classList.add('d-none');
        if (pauseBtn) pauseBtn.classList.remove('d-none');
        logMessage("Starting batch extraction runner (50 items/batch)...");
        runNextBatch();
    }

    function pauseBatchProcessing() {
        runnerActive = false;
        const startBtn = document.getElementById('startBatchRunnerBtn');
        const pauseBtn = document.getElementById('pauseBatchRunnerBtn');
        if (startBtn) startBtn.classList.remove('d-none');
        if (pauseBtn) pauseBtn.classList.add('d-none');
        logMessage("Extraction paused by user.");
    }

    function runNextBatch() {
        if (!runnerActive) return;

        fetch(appUrl('/imports/' + jobId + '/batch'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ batch_size: 50 })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                logMessage(`Batch error: ${data.message}`);
                pauseBatchProcessing();
                return;
            }

            // Update UI Counters
            if (document.getElementById('runnerProgressBar')) {
                document.getElementById('runnerProgressBar').style.width = data.progress_percent + '%';
            }
            if (document.getElementById('runnerPercent')) {
                document.getElementById('runnerPercent').innerText = data.progress_percent + '%';
            }
            if (document.getElementById('runnerImported')) {
                document.getElementById('runnerImported').innerText = data.total_imported;
            }
            if (document.getElementById('runnerSkipped')) {
                document.getElementById('runnerSkipped').innerText = data.total_skipped;
            }
            if (document.getElementById('runnerFailed')) {
                document.getElementById('runnerFailed').innerText = data.total_failed;
            }
            if (document.getElementById('runnerRemaining')) {
                document.getElementById('runnerRemaining').innerText = data.remaining;
            }

            logMessage(`Processed batch: +${data.imported_in_batch} extracted, +${data.skipped_in_batch || 0} skipped, +${data.failed_in_batch} failed. Remaining: ${data.remaining}`);

            if (data.remaining > 0 && runnerActive) {
                setTimeout(runNextBatch, 500); // 0.5s pause to prevent overloading shared hosting PHP worker
            } else {
                runnerActive = false;
                logMessage(`Extraction finished! Job status: ${data.status}`);
                const startBtn = document.getElementById('startBatchRunnerBtn');
                const pauseBtn = document.getElementById('pauseBatchRunnerBtn');
                if (startBtn) startBtn.classList.remove('d-none');
                if (pauseBtn) pauseBtn.classList.add('d-none');
                setTimeout(() => window.location.reload(), 1500);
            }
        })
        .catch(err => {
            logMessage(`Network exception: ${err.message}`);
            pauseBatchProcessing();
        });
    }

    // Toggle single item selection
    function toggleItemSelection(itemId, isChecked) {
        const newStatus = isChecked ? 'pending' : 'skipped';
        fetch(appUrl('/imports/' + jobId + '/selection'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                action: 'toggle',
                item_id: itemId,
                status: newStatus
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('itemStatus-' + itemId);
                if (badge) {
                    badge.innerText = isChecked ? 'Pending' : 'Skipped';
                    badge.className = 'badge ' + (isChecked ? 'bg-secondary' : 'bg-warning-subtle text-warning border border-warning-subtle');
                }
                if (document.getElementById('runnerSkipped')) {
                    document.getElementById('runnerSkipped').innerText = data.skipped;
                }
                if (document.getElementById('runnerRemaining')) {
                    document.getElementById('runnerRemaining').innerText = data.pending;
                }
            }
        });
    }

    // Bulk Select or Deselect All
    function bulkSelect(selectAll) {
        const action = selectAll ? 'select_all' : 'deselect_all';
        fetch(appUrl('/imports/' + jobId + '/selection'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ action: action })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.item-select-cb:not([disabled])').forEach(cb => {
                    cb.checked = selectAll;
                    const id = cb.dataset.id;
                    const badge = document.getElementById('itemStatus-' + id);
                    if (badge && !cb.disabled) {
                        badge.innerText = selectAll ? 'Pending' : 'Skipped';
                        badge.className = 'badge ' + (selectAll ? 'bg-secondary' : 'bg-warning-subtle text-warning border border-warning-subtle');
                    }
                });
                const master = document.getElementById('masterSelectCb');
                if (master) master.checked = selectAll;
                if (document.getElementById('runnerSkipped')) {
                    document.getElementById('runnerSkipped').innerText = data.skipped;
                }
                if (document.getElementById('runnerRemaining')) {
                    document.getElementById('runnerRemaining').innerText = data.pending;
                }
                logMessage(selectAll ? "All entries selected for extraction." : "All entries deselected (skipped).");
            }
        });
    }

    // Master checkbox toggle
    function toggleMasterCheckbox(checked) {
        bulkSelect(checked);
    }

    // Filter manifest table by search input
    function filterManifestTable() {
        const q = (document.getElementById('manifestSearchInput').value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#manifestTableBody .manifest-row');
        rows.forEach(row => {
            const name = row.dataset.name || '';
            const path = row.dataset.path || '';
            if (q === '' || name.includes(q) || path.includes(q)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>
@endsection
