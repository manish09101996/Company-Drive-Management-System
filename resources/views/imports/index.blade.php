@extends('layouts.app')

@section('title', 'Google Takeout & Archive Extractor Center')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-zip text-primary me-2"></i>Google Takeout & Archive Extractor Center</h4>
        <p class="text-muted small mb-0">Decompress and import 10–50 GB Google Takeout ZIP backups securely into Company Drive without timeouts.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('drive.index') }}" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-hdd-network"></i> Open Company Drive
        </a>
    </div>
</div>

<!-- Archives Detected in Company Drive (Top Priority) -->
@if(isset($existingZipFiles) && $existingZipFiles->count() > 0)
<div class="card mb-4 border-primary shadow-sm">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-zip-fill me-2"></i>Uploaded ZIP Archives in Company Drive ({{ $existingZipFiles->count() }})</h6>
        <span class="badge bg-white text-primary fw-bold">1-Click Direct Extractor</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Archive Name</th>
                    <th>Folder Location</th>
                    <th>Size</th>
                    <th>Uploaded By</th>
                    <th>Status</th>
                    <th class="text-end" style="min-width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($existingZipFiles as $zipFile)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-zip text-primary fs-3"></i>
                            <div>
                                <span class="fw-semibold text-dark">{{ $zipFile->name }}</span>
                                <div class="text-muted small font-monospace" style="font-size: 0.7rem;">{{ substr($zipFile->checksum_sha256, 0, 16) }}...</div>
                            </div>
                        </div>
                    </td>
                    <td class="small text-muted">
                        <i class="bi bi-folder2 text-secondary me-1"></i>{{ $zipFile->folder?->name ?? 'Root Storage' }}
                    </td>
                    <td class="small fw-semibold text-dark">{{ $zipFile->formatted_size }}</td>
                    <td class="small text-muted">{{ $zipFile->uploader?->name ?? 'System' }}</td>
                    <td>
                        @if($zipFile->import_job && $zipFile->import_job->status === 'completed')
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Extracted (100%)</span>
                        @elseif($zipFile->import_job && in_array($zipFile->import_job->status, ['processing', 'manifest_ready']))
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>{{ ucfirst(str_replace('_', ' ', $zipFile->import_job->status)) }}</span>
                        @else
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Ready to Extract</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end align-items-center">
                            @if($zipFile->import_job)
                                @if($zipFile->import_job->status === 'completed')
                                    <a href="{{ $zipFile->import_job->destinationFolder ? route('drive.index', $zipFile->import_job->destinationFolder->uuid) : route('drive.index') }}" class="btn btn-sm btn-success fw-semibold shadow-sm" title="View Extracted Files in Drive">
                                        <i class="bi bi-folder2-open me-1"></i> View in Drive
                                    </a>
                                @endif
                                <a href="{{ route('imports.show', $zipFile->import_job->uuid) }}" class="btn btn-sm btn-outline-primary" title="View Manifest & Extraction Details">
                                    <i class="bi bi-eye me-1"></i> Details
                                </a>
                            @else
                                <form action="{{ route('files.extract', $zipFile->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary fw-semibold shadow-sm">
                                        <i class="bi bi-box-arrow-in-down me-1"></i> Extract Archive...
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Import & Extraction Jobs Table -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-list-task me-2 text-primary"></i>Extraction & Takeout Import Jobs</h6>
        <span class="badge bg-light text-dark border">{{ $jobs->total() }} Total Jobs</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Job Title / Archive</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Target Folder</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">{{ $job->title }}</div>
                        <div class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $job->archive_filename }}</div>
                    </td>
                    <td class="small fw-semibold text-dark">{{ $job->formatted_size }}</td>
                    <td>
                        @php
                            $badgeClass = match($job->status) {
                                'pending' => 'bg-secondary',
                                'validating' => 'bg-info',
                                'manifest_ready' => 'bg-info',
                                'processing' => 'bg-warning text-dark',
                                'completed' => 'bg-success',
                                'partially_completed' => 'bg-warning text-dark',
                                'failed' => 'bg-danger',
                                'cancelled' => 'bg-secondary',
                                default => 'bg-light text-dark'
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', strtoupper($job->status)) }}</span>
                    </td>
                    <td style="width: 170px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar {{ $job->status === 'completed' ? 'bg-success' : 'bg-primary' }}" style="width: {{ $job->progress_percentage }}%"></div>
                            </div>
                            <span class="small fw-semibold text-dark" style="font-size: 0.75rem;">{{ $job->progress_percentage }}%</span>
                        </div>
                        <div class="text-muted" style="font-size: 0.7rem;">
                            {{ number_format($job->imported_entries) }} / {{ number_format($job->total_entries) }} items
                        </div>
                    </td>
                    <td class="small text-muted">{{ $job->destinationFolder?->name ?? 'Root Storage' }}</td>
                    <td class="small text-muted">{{ $job->created_at->format('M d, Y H:i') }}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            @if($job->status === 'completed')
                            <a href="{{ $job->destinationFolder ? route('drive.index', $job->destinationFolder->uuid) : route('drive.index') }}" class="btn btn-success fw-semibold" title="View Extracted Files in Drive">
                                <i class="bi bi-folder2-open me-1"></i> Drive
                            </a>
                            @endif

                            @if($job->status === 'pending')
                            <form action="{{ route('imports.inspect', $job->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary" title="Inspect & Generate Manifest">
                                    <i class="bi bi-search me-1"></i> Inspect
                                </button>
                            </form>
                            @else
                            <a href="{{ route('imports.show', $job->uuid) }}" class="btn btn-outline-primary" title="View Extraction Details">
                                <i class="bi bi-eye me-1"></i> View
                            </a>
                            @endif

                            <form action="{{ route('imports.destroy', $job->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this extraction job record?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light border text-danger" title="Delete Job">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted small">
                        <i class="bi bi-box-arrow-in-down display-4 text-secondary d-block mb-2"></i>
                        No Google Takeout imports yet. Select a Takeout ZIP file below to begin.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($jobs->hasPages())
    <div class="card-footer bg-white py-2">
        {{ $jobs->links() }}
    </div>
    @endif
</div>

<!-- Upload New Archive & Storage Section -->
<div class="row g-4 mb-4">
    <!-- Resumable Upload Card -->
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Upload New Takeout Archive</h6>
            </div>
            <div class="card-body">
                <div class="border border-2 border-dashed rounded p-4 text-center mb-3 bg-light" id="archiveDropZone">
                    <i class="bi bi-file-earmark-zip display-4 text-primary d-block mb-2"></i>
                    <h6 class="fw-bold">Upload Takeout ZIP from Computer</h6>
                    <p class="text-muted small mb-3">Resumable 4 MB chunks (safe for 10–50 GB files)</p>
                    <input type="file" id="archiveInput" accept=".zip" class="form-control d-inline-block w-auto">
                </div>

                <div class="mb-3 d-none" id="uploadProgressSection">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span id="chunkStatusText">Uploading chunk 0 of 0</span>
                        <span id="chunkPercentText" class="fw-bold text-primary">0%</span>
                    </div>
                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="chunkProgressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="small text-muted text-center mt-2" id="uploadDetailText"></div>
                </div>

                <button type="button" id="startUploadArchiveBtn" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" onclick="startChunkedUpload()">
                    <i class="bi bi-upload me-1"></i> Start Resumable Upload
                </button>
            </div>
        </div>
    </div>

    <!-- Storage Status & Health -->
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-hdd-network text-info me-2"></i>Hostinger Server Storage Capacity (50 GB NVMe)</h6>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-check-circle-fill me-1"></i>Healthy ({{ $storageStats['free_formatted'] }} Free)
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-sm-4 text-center">
                        <div class="text-muted small">FREE STORAGE</div>
                        <div class="fs-4 fw-bold text-success">{{ $storageStats['free_formatted'] }}</div>
                    </div>
                    <div class="col-sm-4 text-center">
                        <div class="text-muted small">STORAGE USED</div>
                        <div class="fs-4 fw-bold text-primary">{{ $storageStats['used_formatted'] }}</div>
                    </div>
                    <div class="col-sm-4 text-center">
                        <div class="text-muted small">TOTAL PLAN</div>
                        <div class="fs-4 fw-bold text-dark">{{ $storageStats['total_formatted'] }}</div>
                    </div>
                </div>

                <div class="progress mb-2" style="height: 12px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ max(1, $storageStats['used_percentage']) }}%"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-3">
                    <span>Storage Usage: {{ $storageStats['used_percentage'] }}% ({{ $storageStats['used_formatted'] }} of {{ $storageStats['total_formatted'] }})</span>
                    <span>Safe threshold: &lt; 90%</span>
                </div>

                <div class="row g-2 text-center small text-muted mb-2 border rounded p-2 bg-light">
                    <div class="col-4">
                        <span class="d-block fw-semibold text-dark">{{ $storageStats['inodes_formatted'] }} / {{ $storageStats['inodes_limit_formatted'] }}</span>
                        <span style="font-size: 0.7rem;">Inodes ({{ $storageStats['inodes_percentage'] }}%)</span>
                    </div>
                    <div class="col-4">
                        <span class="d-block fw-semibold text-dark">{{ $storageStats['php_workers'] }} Workers</span>
                        <span style="font-size: 0.7rem;">PHP Process Pool</span>
                    </div>
                    <div class="col-4">
                        <span class="d-block fw-semibold text-dark">{{ $storageStats['mysql_connections'] }} Max</span>
                        <span style="font-size: 0.7rem;">MySQL Conns</span>
                    </div>
                </div>

                <hr class="my-3">

                <div class="small text-muted">
                    <div class="fw-semibold text-dark mb-1"><i class="bi bi-terminal me-1"></i>Command Line / Background Worker:</div>
                    <p class="mb-0 font-monospace bg-light p-2 rounded border">
                        php artisan drive:import-process
                    </p>
                    <div class="mt-1" style="font-size: 0.75rem;">Can be scheduled in Hostinger cPanel cron to process batches in the background automatically!</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const CHUNK_SIZE = 4 * 1024 * 1024; // 4MB chunks for shared hosting reliability

    function startChunkedUpload() {
        const fileInput = document.getElementById('archiveInput');
        if (!fileInput.files.length) {
            alert('Please select a ZIP archive file to upload.');
            return;
        }

        const file = fileInput.files[0];
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const uploadId = 'takeout_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);

        document.getElementById('uploadProgressSection').classList.remove('d-none');
        document.getElementById('startUploadArchiveBtn').disabled = true;

        uploadNextChunk(file, uploadId, 0, totalChunks);
    }

    function uploadNextChunk(file, uploadId, chunkIndex, totalChunks) {
        const start = chunkIndex * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunkBlob = file.slice(start, end);

        const formData = new FormData();
        formData.append('chunk', chunkBlob);
        formData.append('upload_id', uploadId);
        formData.append('chunk_index', chunkIndex);
        formData.append('total_chunks', totalChunks);
        formData.append('original_filename', file.name);

        const percent = Math.round((chunkIndex / totalChunks) * 100);
        document.getElementById('chunkProgressBar').style.width = percent + '%';
        document.getElementById('chunkPercentText').innerText = percent + '%';
        document.getElementById('chunkStatusText').innerText = `Uploading chunk ${chunkIndex + 1} of ${totalChunks}...`;
        document.getElementById('uploadDetailText').innerText = `${((end) / (1024 * 1024)).toFixed(1)} MB / ${(file.size / (1024 * 1024)).toFixed(1)} MB`;

        fetch(appUrl('/imports/chunk'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Upload error: ' + (data.message || 'Unknown error'));
                document.getElementById('startUploadArchiveBtn').disabled = false;
                return;
            }

            if (data.complete) {
                document.getElementById('chunkProgressBar').style.width = '100%';
                document.getElementById('chunkPercentText').innerText = '100%';
                document.getElementById('chunkStatusText').innerText = 'Reassembly complete! Inspecting archive...';
                setTimeout(() => {
                    window.location.href = appUrl('/imports/' + data.job_uuid);
                }, 1000);
            } else {
                uploadNextChunk(file, uploadId, chunkIndex + 1, totalChunks);
            }
        })
        .catch(err => {
            alert('Upload interrupted: ' + err.message);
            document.getElementById('startUploadArchiveBtn').disabled = false;
        });
    }
</script>
@endsection
