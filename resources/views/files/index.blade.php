@extends('layouts.app')

@section('title', $currentFolder ? $currentFolder->name : 'Company Drive')

@section('content')
<style>
    .folder-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .folder-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
    }
    .folder-file-row {
        transition: background-color 0.12s ease;
    }
    .folder-file-row:hover {
        background-color: #f1f5f9;
    }
    .folder-files-container {
        font-size: 0.85rem;
    }
</style>

<!-- Header & Breadcrumbs -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="{{ route('drive.index') }}" class="text-decoration-none">
                        <i class="bi bi-hdd-network-fill me-1 text-primary"></i> Company Drive
                    </a>
                </li>
                @foreach($breadcrumbs as $crumb)
                    @if($loop->last)
                        <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">{{ $crumb['name'] }}</li>
                    @else
                        <li class="breadcrumb-item">
                            <a href="{{ route('drive.index', $crumb['uuid']) }}" class="text-decoration-none">{{ $crumb['name'] }}</a>
                        </li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h4 class="fw-bold mb-0">
            @if($currentFolder)
                <i class="bi bi-folder2-open me-2" style="color: {{ $currentFolder->color }}"></i>{{ $currentFolder->name }}
            @else
                <i class="bi bi-folder-fill me-2 text-primary"></i>Root Storage
            @endif
        </h4>
    </div>

    <!-- Quick Actions -->
    <div class="d-flex flex-wrap gap-2">
        @if(auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('imports.manage') || auth()->user()->hasPermission('files.upload'))
        <a href="{{ route('imports.index') }}" class="btn btn-dark d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-box-arrow-in-down"></i> Import Takeout ZIP
        </a>
        @endif
        @if(auth()->user()->isSuperAdmin())
        <button class="btn btn-outline-primary d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#newFolderModal">
            <i class="bi bi-folder-plus"></i> New Folder
        </button>
        <button class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
            <i class="bi bi-cloud-arrow-up"></i> Upload Files
        </button>
        @else
            @if($currentFolder)
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                <i class="bi bi-folder-plus"></i> New Folder
            </button>
            <button class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                <i class="bi bi-cloud-arrow-up"></i> Upload Files
            </button>
            @endif
        @endif
    </div>
</div>

@if(!$currentFolder && !auth()->user()->isSuperAdmin())
<div class="alert alert-light border border-primary-subtle d-flex align-items-center justify-content-between p-3 mb-4 shadow-sm rounded-3">
    <div class="d-flex align-items-center gap-3">
        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle fs-4">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div>
            <div class="fw-bold text-dark">Welcome, {{ auth()->user()->name }}</div>
            <div class="text-muted small">You have dedicated private access to your assigned company folder below. All documents within your workspace are secured.</div>
        </div>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2">
        <i class="bi bi-person-check-fill me-1"></i>Private Workspace
    </span>
</div>
@endif

<!-- Search & Filter Bar -->
<div class="card mb-4 p-3">
    <form action="{{ $currentFolder ? route('drive.index', $currentFolder->uuid) : route('drive.index') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search files & folders..." value="{{ request('search') }}">
            </div>
        </div>
        <div class="col-sm-4 col-md-3">
            <select name="type" class="form-select" onchange="this.form.submit()">
                <option value="">All File Types</option>
                <option value="documents" {{ request('type') == 'documents' ? 'selected' : '' }}>Documents</option>
                <option value="images" {{ request('type') == 'images' ? 'selected' : '' }}>Images</option>
                <option value="archives" {{ request('type') == 'archives' ? 'selected' : '' }}>Archives (ZIP)</option>
                <option value="media" {{ request('type') == 'media' ? 'selected' : '' }}>Audio / Video</option>
            </select>
        </div>
        <div class="col-sm-4 col-md-2">
            <select name="sort" class="form-select" onchange="this.form.submit()">
                <option value="name" {{ request('sort') == 'name' ? 'selected' : '' }}>Name</option>
                <option value="created_at" {{ request('sort') == 'created_at' ? 'selected' : '' }}>Date</option>
                <option value="size_bytes" {{ request('sort') == 'size_bytes' ? 'selected' : '' }}>Size</option>
            </select>
        </div>
        <div class="col-sm-4 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-dark w-100">Filter</button>
            @if(request()->hasAny(['search', 'type', 'sort']))
                <a href="{{ $currentFolder ? route('drive.index', $currentFolder->uuid) : route('drive.index') }}" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Search Results Notice -->
@if(request()->filled('search'))
<div class="alert alert-info d-flex align-items-center justify-content-between mb-4 shadow-sm" role="alert">
    <div>
        <i class="bi bi-search me-2 text-primary fs-5"></i>
        <span>
            Search results for <strong>"{{ request('search') }}"</strong>: found 
            <strong>{{ $files->count() }} {{ \Illuminate\Support\Str::plural('file', $files->count()) }}</strong>
            and <strong>{{ $folders->count() }} {{ \Illuminate\Support\Str::plural('folder', $folders->count()) }}</strong>
            @if($currentFolder)
                within <strong>{{ $currentFolder->name }}</strong>
            @else
                across the entire Company Drive
            @endif
        </span>
    </div>
    <a href="{{ $currentFolder ? route('drive.index', $currentFolder->uuid) : route('drive.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x-circle me-1"></i> Clear Search
    </a>
</div>
@endif

<!-- Folders Section -->
@if($folders->count() > 0)
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-uppercase text-muted fw-semibold small mb-0">Folders ({{ $folders->count() }})</h6>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small d-none d-md-inline" style="font-size: 0.75rem;"><i class="bi bi-files me-1"></i>Direct files preview enabled</span>
            <div class="btn-group btn-group-sm border rounded bg-white shadow-xs" role="group" aria-label="View toggle">
                <button type="button" class="btn btn-sm btn-primary active px-2" id="btnGridView" onclick="setDriveView('grid')" title="Grid View (Cards)">
                    <i class="bi bi-grid-fill me-1"></i>Grid
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary border-0 px-2" id="btnListView" onclick="setDriveView('list')" title="List View (Table)">
                    <i class="bi bi-list-ul me-1"></i>List
                </button>
            </div>
        </div>
    </div>
    <div id="foldersGridView" class="row g-3">
        @foreach($folders as $folder)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm border hover-shadow folder-card" style="border-left: 4px solid {{ $folder->color }} !important;">
                <!-- Header -->
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                        <i class="bi bi-folder-fill fs-4 flex-shrink-0" style="color: {{ $folder->color }}"></i>
                        <a href="{{ route('drive.index', $folder->uuid) }}" class="fw-bold text-dark text-truncate text-decoration-none" title="{{ $folder->name }}">
                            {{ $folder->name }}
                        </a>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        @php
                            $subCount = $folder->children_count ?? $folder->children()->count();
                            $fileCount = $folder->files->count();
                        @endphp
                        @if($subCount > 0 && $fileCount > 0)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small" style="font-size: 0.7rem;">
                                {{ $subCount }} {{ \Illuminate\Support\Str::plural('folder', $subCount) }}, {{ $fileCount }} {{ \Illuminate\Support\Str::plural('file', $fileCount) }}
                            </span>
                        @elseif($subCount > 0)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small" style="font-size: 0.7rem;">
                                <i class="bi bi-folder2 me-1"></i>{{ $subCount }} {{ \Illuminate\Support\Str::plural('folder', $subCount) }}
                            </span>
                        @else
                            <span class="badge bg-light text-secondary border small" style="font-size: 0.7rem;">
                                {{ $fileCount }} {{ \Illuminate\Support\Str::plural('file', $fileCount) }}
                            </span>
                        @endif
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light border-0" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item" href="{{ route('drive.index', $folder->uuid) }}">
                                    <i class="bi bi-folder2-open me-2 text-primary"></i>Open Folder
                                </a>
                            </li>
                            @can('update', $folder)
                            <li>
                                <button class="dropdown-item" onclick="openRenameModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')">
                                    <i class="bi bi-pencil me-2 text-warning"></i>Rename
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item" onclick="openMoveModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')">
                                    <i class="bi bi-folder-symlink me-2 text-info"></i>Move
                                </button>
                            </li>
                            @endcan
                            @can('share', $folder)
                            <li>
                                <button class="dropdown-item" onclick="openShareModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')">
                                    <i class="bi bi-share me-2 text-success"></i>Share & Permissions
                                </button>
                            </li>
                            @endcan
                            @can('delete', $folder)
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('folders.destroy', $folder->id) }}" method="POST" onsubmit="return confirm('Move folder \'{{ $folder->name }}\' and its contents to Recycle Bin?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash3 me-2"></i>Delete Folder
                                    </button>
                                </form>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Direct Files Preview Inside Folder Card -->
            <div class="card-body p-2 d-flex flex-column justify-content-between">
                @if($folder->files->count() > 0)
                    <div class="folder-files-container">
                        @php
                            $previewLimit = 4;
                            $visibleFiles = $folder->files->take($previewLimit);
                            $remainingFiles = $folder->files->slice($previewLimit);
                        @endphp

                        {{-- First 3-4 files directly visible --}}
                        @foreach($visibleFiles as $file)
                        <div class="folder-file-row d-flex align-items-center justify-content-between p-1 px-2 rounded mb-1">
                            <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                <i class="bi {{ $file->icon_class }} fs-5 flex-shrink-0"></i>
                                <div class="text-truncate">
                                    <a href="javascript:void(0)" 
                                       onclick="openPreviewModal('{{ addslashes($file->name) }}', '{{ route('files.preview', $file->id) }}', '{{ $file->effective_mime_type }}', '{{ route('files.download', $file->id) }}', {{ (int)$file->size_bytes }}, '{{ $file->formatted_size }}', {{ $file->id }})" 
                                       class="text-dark fw-semibold small text-decoration-none text-truncate d-inline-block" 
                                       style="max-width: 210px;" 
                                       title="View / Preview: {{ $file->name }}">
                                        {{ $file->name }}
                                    </a>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                <span class="text-muted font-monospace me-1" style="font-size: 0.72rem;">{{ $file->formatted_size }}</span>
                                <a href="javascript:void(0)" 
                                   onclick="openPreviewModal('{{ addslashes($file->name) }}', '{{ route('files.preview', $file->id) }}', '{{ $file->effective_mime_type }}', '{{ route('files.download', $file->id) }}', {{ (int)$file->size_bytes }}, '{{ $file->formatted_size }}', {{ $file->id }})" 
                                   class="btn btn-sm btn-link text-primary p-0 px-1" 
                                   title="View / Preview">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('files.download', $file->id) }}" 
                                   class="btn btn-sm btn-link text-secondary p-0 px-1" 
                                   title="Download File">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                        @endforeach

                        {{-- Collapsible remaining files if > 4 --}}
                        @if($remainingFiles->count() > 0)
                        <div class="collapse" id="collapseFolder-{{ $folder->id }}">
                            @foreach($remainingFiles as $file)
                            <div class="folder-file-row d-flex align-items-center justify-content-between p-1 px-2 rounded mb-1">
                                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                    <i class="bi {{ $file->icon_class }} fs-5 flex-shrink-0"></i>
                                    <div class="text-truncate">
                                        <a href="javascript:void(0)" 
                                           onclick="openPreviewModal('{{ addslashes($file->name) }}', '{{ route('files.preview', $file->id) }}', '{{ $file->effective_mime_type }}', '{{ route('files.download', $file->id) }}', {{ (int)$file->size_bytes }}, '{{ $file->formatted_size }}', {{ $file->id }})" 
                                           class="text-dark fw-semibold small text-decoration-none text-truncate d-inline-block" 
                                           style="max-width: 210px;" 
                                           title="View / Preview: {{ $file->name }}">
                                            {{ $file->name }}
                                        </a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <span class="text-muted font-monospace me-1" style="font-size: 0.72rem;">{{ $file->formatted_size }}</span>
                                    <a href="javascript:void(0)" 
                                       onclick="openPreviewModal('{{ addslashes($file->name) }}', '{{ route('files.preview', $file->id) }}', '{{ $file->effective_mime_type }}', '{{ route('files.download', $file->id) }}', {{ (int)$file->size_bytes }}, '{{ $file->formatted_size }}', {{ $file->id }})" 
                                       class="btn btn-sm btn-link text-primary p-0 px-1" 
                                       title="View / Preview">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('files.download', $file->id) }}" 
                                       class="btn btn-sm btn-link text-secondary p-0 px-1" 
                                       title="Download File">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Inline Expand/Collapse Button --}}
                        <div class="pt-1 text-center border-top mt-1">
                            <button class="btn btn-sm btn-link text-decoration-none text-primary fw-semibold p-0 d-inline-flex align-items-center gap-1" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#collapseFolder-{{ $folder->id }}" 
                                    aria-expanded="false" 
                                    onclick="toggleFolderExpand(this, {{ $remainingFiles->count() }})">
                                <i class="bi bi-chevron-down"></i>
                                <span class="expand-label">+{{ $remainingFiles->count() }} more files</span>
                            </button>
                        </div>
                        @endif
                    </div>
                @elseif(($folder->children_count ?? $folder->children()->count()) > 0)
                    @php $subCount = $folder->children_count ?? $folder->children()->count(); @endphp
                    <div class="p-3 text-center text-muted small">
                        <i class="bi bi-folder-symlink text-primary d-block fs-3 mb-1"></i>
                        <span class="fw-semibold text-dark">{{ $subCount }} {{ \Illuminate\Support\Str::plural('subfolder', $subCount) }} inside</span>
                        <div class="text-muted small mt-1">Contains nested folders & files &bull; Click <em>Open Folder &rarr;</em></div>
                    </div>
                @else
                    <div class="p-3 text-center text-muted small">
                        <i class="bi bi-folder2 text-secondary d-block fs-4 mb-1"></i>
                        <span>Empty folder (0 files)</span>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="card-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center small text-muted" style="font-size: 0.72rem;">
                <span>
                    @if($folder->assignedUser)
                        <i class="bi bi-person-fill me-1 text-primary"></i>{{ $folder->assignedUser->name }}
                    @else
                        <i class="bi bi-building me-1"></i>{{ $folder->department?->code ?? 'Global' }}
                    @endif
                </span>
                <a href="{{ route('drive.index', $folder->uuid) }}" class="text-primary text-decoration-none fw-semibold">
                    Open Folder &rarr;
                </a>
            </div>
        </div>
    </div>
        @endforeach
    </div>

    <!-- Folders List / Table View -->
    <div id="foldersListView" class="card shadow-sm border mb-4 d-none">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th style="min-width: 250px;">Folder Name</th>
                        <th style="min-width: 140px;">Contents</th>
                        <th style="min-width: 150px;">Assigned User</th>
                        <th class="text-end" style="min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($folders as $folder)
                    @php
                        $subCount = $folder->children_count ?? $folder->children()->count();
                        $fileCount = $folder->files->count();
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-folder-fill fs-5" style="color: {{ $folder->color }}"></i>
                                <a href="{{ route('drive.index', $folder->uuid) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $folder->name }}
                                </a>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border small">
                                @if($subCount > 0)
                                    {{ $subCount }} {{ \Illuminate\Support\Str::plural('folder', $subCount) }}@if($fileCount > 0), @endif
                                @endif
                                @if($fileCount > 0 || $subCount === 0)
                                    {{ $fileCount }} {{ \Illuminate\Support\Str::plural('file', $fileCount) }}
                                @endif
                            </span>
                        </td>
                        <td class="small text-muted">
                            @if($folder->assignedUser)
                                <i class="bi bi-person-fill me-1 text-primary"></i>{{ $folder->assignedUser->name }}
                            @else
                                <i class="bi bi-building me-1"></i>{{ $folder->department?->code ?? 'Global' }}
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('drive.index', $folder->uuid) }}" class="btn btn-sm btn-outline-primary fw-semibold">
                                <i class="bi bi-folder2-open me-1"></i>Open Folder
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Files Section -->
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-uppercase text-muted fw-semibold small mb-0">Files ({{ $files->count() }})</h6>
    </div>

    @if($files->count() > 0)
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th>Checksum (SHA-256)</th>
                        <th class="text-end" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($files as $file)
                    <tr>
                        <td class="text-center">
                            <i class="bi {{ $file->icon_class }} fs-4"></i>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 280px;" title="{{ $file->name }}">
                                        <a href="javascript:void(0)" onclick="openPreviewModal('{{ addslashes($file->name) }}', '{{ route('files.preview', $file->id) }}', '{{ $file->effective_mime_type }}', '{{ route('files.download', $file->id) }}', {{ (int)$file->size_bytes }}, '{{ $file->formatted_size }}', {{ $file->id }})" class="text-decoration-none text-dark" title="Click to View: {{ $file->name }}">
                                            {{ $file->name }}
                                        </a>
                                        @if($file->extension === 'zip' || str_ends_with(strtolower($file->name), '.zip'))
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.65rem;">ZIP Archive</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.72rem;">
                                        <span>{{ $file->mime_type }}</span>
                                        @if($file->folder)
                                        <span>•</span>
                                        <a href="{{ route('drive.index', $file->folder->uuid) }}" class="text-decoration-none text-muted" title="Located in folder: {{ $file->folder->name }}">
                                            <i class="bi bi-folder2 text-primary me-1"></i>{{ $file->folder->name }}
                                        </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="small fw-semibold text-dark">{{ $file->formatted_size }}</td>
                        <td class="small text-muted">{{ $file->uploader?->name ?? 'System' }}</td>
                        <td class="small text-muted">{{ $file->created_at->format('M d, Y H:i') }}</td>
                        <td class="small text-muted font-monospace" style="font-size: 0.7rem;" title="{{ $file->checksum_sha256 }}">
                            {{ substr($file->checksum_sha256, 0, 12) }}...{{ substr($file->checksum_sha256, -6) }}
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-light border text-primary" onclick="openPreviewModal('{{ addslashes($file->name) }}', '{{ route('files.preview', $file->id) }}', '{{ $file->effective_mime_type }}', '{{ route('files.download', $file->id) }}', {{ (int)$file->size_bytes }}, '{{ $file->formatted_size }}', {{ $file->id }})" title="View / Preview File">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <a href="{{ route('files.download', $file->id) }}" class="btn btn-light border" title="Download File">
                                    <i class="bi bi-download"></i>
                                </a>
                                @if($file->extension === 'zip' || str_ends_with(strtolower($file->name), '.zip'))
                                <form action="{{ route('files.extract', $file->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-primary" title="Extract ZIP Archive into Drive">
                                        <i class="bi bi-file-earmark-zip"></i>
                                    </button>
                                </form>
                                @endif
                                <button type="button" class="btn btn-light border dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('files.preview', $file->id) }}" target="_blank">
                                            <i class="bi bi-box-arrow-up-right me-2 text-primary"></i>View in New Tab
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    @if($file->extension === 'zip' || str_ends_with(strtolower($file->name), '.zip'))
                                    <li>
                                        <form action="{{ route('files.extract', $file->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-primary fw-semibold">
                                                <i class="bi bi-file-earmark-zip me-2 text-primary"></i>Extract Archive...
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    @endif
                                    <li>
                                        <button class="dropdown-item" onclick="openVersionModal({{ $file->id }}, '{{ addslashes($file->name) }}')">
                                            <i class="bi bi-clock-history me-2 text-info"></i>Version History
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="openRenameModal('file', {{ $file->id }}, '{{ addslashes($file->name) }}')">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Rename
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="openMoveModal('file', {{ $file->id }}, '{{ addslashes($file->name) }}')">
                                            <i class="bi bi-folder-symlink me-2 text-primary"></i>Move
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="openShareModal('file', {{ $file->id }}, '{{ addslashes($file->name) }}')">
                                            <i class="bi bi-share me-2 text-success"></i>Share & Permissions
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('files.destroy', $file->id) }}" method="POST" onsubmit="return confirm('Move \'{{ $file->name }}\' to Recycle Bin?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash3 me-2"></i>Delete File
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
        @if($folders->count() === 0)
        <div class="card p-5 text-center text-muted">
            <i class="bi bi-folder2-open display-4 text-secondary mb-3"></i>
            <h5 class="fw-bold text-dark">This folder is empty</h5>
            <p class="small mb-3">Upload documents or create a new subfolder to organize your company files.</p>
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload Files
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                    <i class="bi bi-folder-plus me-1"></i> New Folder
                </button>
            </div>
        </div>
        @endif
    @endif
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. New Folder Modal -->
<div class="modal fade" id="newFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('folders.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-folder-plus text-primary me-2"></i>Create New Folder</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Folder Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Q4 Financials">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Department (Optional)</label>
                        <select name="department_id" class="form-select">
                            <option value="">Company-Wide / Shared</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ $currentFolder?->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Folder Color</label>
                        <select name="color" class="form-select">
                            <option value="#4f46e5">Indigo</option>
                            <option value="#2563eb">Blue</option>
                            <option value="#059669">Green</option>
                            <option value="#ea580c">Orange</option>
                            <option value="#dc2626">Red</option>
                            <option value="#7c3aed">Purple</option>
                        </select>
                    </div>
                </div>
                @if(!$currentFolder && auth()->user()->isSuperAdmin())
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Assign to User</label>
                    <select name="assigned_user_id" class="form-select">
                        <option value="">None (Super Admin Only)</option>
                        @foreach($allUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    <div class="form-text small">Assigned user will receive dedicated access to this folder and its subcontents.</div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Folder</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Upload File(s) Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="uploadForm" action="{{ route('files.upload') }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $currentFolder?->id }}">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Upload File(s)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Detected Google Takeout / Drive ZIP Notice -->
                <div id="takeoutZipDetectedAlert" class="alert alert-info d-none p-3 mb-3 border border-info-subtle">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-box-arrow-in-down fs-4 text-primary mt-1"></i>
                        <div>
                            <div class="fw-bold">Google Takeout / Drive Archive Detected</div>
                            <div class="small text-muted mb-2">
                                You selected a ZIP backup archive: <span id="detectedZipName" class="font-monospace fw-semibold text-dark"></span>.
                                <br>To automatically inspect, verify integrity, and <strong>extract all nested folders & files</strong> into Company Drive, please use the <strong>Google Takeout Import Center</strong>!
                            </div>
                            <a href="{{ route('imports.index') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-box-arrow-in-down me-1"></i> Go to Google Takeout Import Center &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <div class="border border-2 border-dashed rounded p-4 text-center mb-3 bg-light" id="dropZone">
                    <i class="bi bi-cloud-arrow-up display-4 text-primary d-block mb-2"></i>
                    <h6 class="fw-bold">Drag and drop files here</h6>
                    <p class="text-muted small mb-3">Supports any file size (multi-GB files uploaded in safe chunks)</p>
                    <input type="file" name="files[]" id="fileInput" class="form-control d-inline-block w-auto" multiple required>
                    <div class="small text-muted mt-2">All files stored privately with SHA-256 integrity verification.</div>
                </div>

                <div class="progress mb-2 d-none" id="uploadProgressContainer" style="height: 12px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="uploadProgressBar" role="progressbar" style="width: 0%"></div>
                </div>
                <div id="uploadStatusText" class="small text-muted text-center d-none">Uploading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="startUploadBtn" class="btn btn-primary">Upload Now</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="renameForm" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil text-warning me-2"></i>Rename Item</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">New Name</label>
                    <input type="text" id="renameInput" name="name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Name</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Move Modal -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="moveForm" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-folder-symlink text-primary me-2"></i>Move Item</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Destination Folder</label>
                    <select name="target_folder_id" class="form-select">
                        <option value="">Root Storage (Top Level)</option>
                        @foreach($allSystemFolders as $sysFolder)
                        <option value="{{ $sysFolder->id }}">{{ $sysFolder->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Move Here</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Share & Permissions Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-shield-check text-success me-2"></i>Manage Permissions: <span id="shareItemName"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Add User Permission Form -->
                <div class="card p-3 bg-light mb-3">
                    <h6 class="fw-semibold small mb-2">Grant User Permission</h6>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small text-muted">User</label>
                            <select id="shareUserSelect" class="form-select form-select-sm">
                                <option value="">Select an active user...</option>
                                @foreach($allUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-muted d-block">Allowed Actions</label>
                            <div class="d-flex flex-wrap gap-2">
                                <div class="form-check form-check-inline small">
                                    <input class="form-check-input" type="checkbox" id="permView" checked disabled>
                                    <label class="form-check-label" for="permView">View</label>
                                </div>
                                <div class="form-check form-check-inline small">
                                    <input class="form-check-input" type="checkbox" id="permDownload" checked>
                                    <label class="form-check-label" for="permDownload">Download</label>
                                </div>
                                <div class="form-check form-check-inline small">
                                    <input class="form-check-input" type="checkbox" id="permUpload">
                                    <label class="form-check-label" for="permUpload">Upload</label>
                                </div>
                                <div class="form-check form-check-inline small">
                                    <input class="form-check-input" type="checkbox" id="permEdit">
                                    <label class="form-check-label" for="permEdit">Edit</label>
                                </div>
                                <div class="form-check form-check-inline small">
                                    <input class="form-check-input" type="checkbox" id="permDelete">
                                    <label class="form-check-label" for="permDelete">Delete</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-2">
                        <button type="button" class="btn btn-sm btn-primary" onclick="submitSharePermission()">
                            <i class="bi bi-plus-circle me-1"></i> Grant Permission
                        </button>
                    </div>
                </div>

                <!-- Existing Permissions Table -->
                <h6 class="fw-semibold small text-muted mb-2">Explicit Item Permissions</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>View</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Edit</th>
                                <th>Delete</th>
                                <th class="text-end">Revoke</th>
                            </tr>
                        </thead>
                        <tbody id="sharePermissionsList">
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">Loading permissions...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- 6. Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header d-flex justify-content-between align-items-center bg-light">
                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                    <i class="bi bi-file-earmark-text text-primary fs-5"></i>
                    <h6 class="modal-title fw-bold text-truncate mb-0" id="previewModalTitle">Document Preview</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a id="previewModalNewTab" href="#" target="_blank" class="btn btn-sm btn-outline-primary" title="Open in new window / full tab">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Full View
                    </a>
                    <a id="previewModalDownload" href="#" class="btn btn-sm btn-outline-secondary" download title="Download file">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0 text-center bg-dark" style="min-height: 550px;" id="previewModalBody">
                <!-- Content injected dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- 7. Version History Modal -->
<div class="modal fade" id="versionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-clock-history text-info me-2"></i>Version History: <span id="versionFileName"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Upload New Version Form -->
                <form id="uploadVersionForm" method="POST" enctype="multipart/form-data" class="card p-3 bg-light mb-3">
                    @csrf
                    <h6 class="fw-semibold small mb-2">Upload New Version</h6>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <input type="file" name="version_file" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="change_summary" class="form-control form-control-sm" placeholder="Summary of changes...">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100">Upload</button>
                        </div>
                    </div>
                </form>

                <p class="small text-muted">All previous versions are preserved privately with full SHA-256 integrity checksums.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let currentShareType = null;
    let currentShareId = null;

    // Open Rename Modal
    function openRenameModal(type, id, currentName) {
        const form = document.getElementById('renameForm');
        form.action = type === 'folder' ? appUrl('/folders/' + id) : appUrl('/files/' + id);
        document.getElementById('renameInput').value = currentName;
        new bootstrap.Modal(document.getElementById('renameModal')).show();
    }

    // Open Move Modal
    function openMoveModal(type, id, name) {
        const form = document.getElementById('moveForm');
        form.action = type === 'folder' ? appUrl('/folders/' + id + '/move') : appUrl('/files/' + id + '/move');
        new bootstrap.Modal(document.getElementById('moveModal')).show();
    }

    // Open Share Modal
    function openShareModal(type, id, name) {
        currentShareType = type;
        currentShareId = id;
        document.getElementById('shareItemName').innerText = name;
        loadPermissions();
        new bootstrap.Modal(document.getElementById('shareModal')).show();
    }

    function loadPermissions() {
        const tbody = document.getElementById('sharePermissionsList');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-2 text-muted">Loading...</td></tr>';

        fetch(appUrl('/share/permissions?type=' + currentShareType + '&id=' + currentShareId))
            .then(res => res.json())
            .then(data => {
                if (!data.success || data.permissions.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-2 text-muted small">No custom user permissions assigned. Standard role access applies.</td></tr>';
                    return;
                }

                tbody.innerHTML = data.permissions.map(p => `
                    <tr>
                        <td class="fw-semibold">${p.user.name} (${p.user.email})</td>
                        <td class="text-center">${p.can_view ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-x-lg text-muted"></i>'}</td>
                        <td class="text-center">${p.can_download ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-x-lg text-muted"></i>'}</td>
                        <td class="text-center">${p.can_upload ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-x-lg text-muted"></i>'}</td>
                        <td class="text-center">${p.can_edit ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-x-lg text-muted"></i>'}</td>
                        <td class="text-center">${p.can_delete ? '<i class="bi bi-check-lg text-success"></i>' : '<i class="bi bi-x-lg text-muted"></i>'}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="revokePermission(${p.user_id})">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            });
    }

    function submitSharePermission() {
        const userId = document.getElementById('shareUserSelect').value;
        if (!userId) {
            alert('Please select a user.');
            return;
        }

        const payload = {
            type: currentShareType,
            id: currentShareId,
            user_id: userId,
            can_view: true,
            can_download: document.getElementById('permDownload').checked,
            can_upload: document.getElementById('permUpload').checked,
            can_edit: document.getElementById('permEdit').checked,
            can_delete: document.getElementById('permDelete').checked,
            can_share: false
        };

        fetch(appUrl('/share/permissions'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadPermissions();
            } else {
                alert(data.message || 'Error updating permission.');
            }
        });
    }

    function revokePermission(userId) {
        if (!confirm('Revoke explicit permission for this user?')) return;

        fetch(appUrl('/share/permissions'), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                type: currentShareType,
                id: currentShareId,
                user_id: userId
            })
        })
        .then(res => res.json())
        .then(() => loadPermissions());
    }

    // Open File Preview Modal
    function openPreviewModal(filename, previewUrl, mimeType, downloadUrl = null, sizeBytes = null, formattedSize = null, fileId = null) {
        document.getElementById('previewModalTitle').innerText = filename + (formattedSize ? ' (' + formattedSize + ')' : '');
        const newTabBtn = document.getElementById('previewModalNewTab');
        const downloadBtn = document.getElementById('previewModalDownload');
        if (newTabBtn) {
            newTabBtn.href = previewUrl;
        }
        if (downloadBtn) {
            downloadBtn.href = downloadUrl || previewUrl;
            downloadBtn.style.display = 'inline-flex';
        }

        const body = document.getElementById('previewModalBody');
        const lowerName = filename.toLowerCase();
        const ext = lowerName.includes('.') ? lowerName.split('.').pop() : '';
        const mime = (mimeType || '').toLowerCase();

        // If file is 0 bytes (empty or placeholder), show clear empty state with replacement option
        if (sizeBytes !== null && parseInt(sizeBytes) === 0) {
            body.className = 'modal-body p-5 text-center bg-dark text-light';
            body.innerHTML = `
                <div class="py-4">
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-x text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2 text-break">${filename}</h5>
                    <div class="mb-3">
                        <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-3 py-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Empty Document (0 Bytes)
                        </span>
                    </div>
                    <p class="text-secondary mx-auto mb-4" style="max-width: 480px; font-size: 0.95rem;">
                        This document has a recorded size of 0 Bytes and cannot be rendered in the document previewer.
                        This can occur when an export includes a Google Drive shortcut, or when an upload was interrupted.
                    </p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        ${fileId ? `
                        <button type="button" class="btn btn-primary" onclick="const m = bootstrap.Modal.getInstance(document.getElementById('previewModal')); if(m) m.hide(); openVersionModal(${fileId}, '${filename.replace(/'/g, "\\'")}')">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload Replacement Version
                        </button>
                        ` : ''}
                        <a href="${downloadUrl || previewUrl}" class="btn btn-outline-light" download>
                            <i class="bi bi-download me-1"></i> Download File (0 B)
                        </a>
                    </div>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('previewModal')).show();
            return;
        }

        if (mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
            body.className = 'modal-body p-3 text-center bg-dark d-flex align-items-center justify-content-center';
            body.innerHTML = `<img src="${previewUrl}" class="img-fluid rounded shadow" style="max-height: 80vh; max-width: 100%; object-fit: contain;">`;
        } else if (mime === 'application/pdf' || ext === 'pdf') {
            body.className = 'modal-body p-0 text-center bg-dark position-relative';
            body.innerHTML = `
                <div id="pdfLoadingSpinner" class="d-flex flex-column align-items-center justify-content-center text-white py-5" style="position: absolute; width: 100%; height: 100%; min-height: 720px; top: 0; left: 0; z-index: 1; background: rgba(15, 23, 42, 0.95);">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div class="small text-light">Loading document preview...</div>
                </div>
                <iframe src="${previewUrl}#toolbar=1" width="100%" height="720px" style="border:none; display:block; background:#fff; position: relative; z-index: 2;" onload="document.getElementById('pdfLoadingSpinner')?.remove()"></iframe>
            `;
        } else if (mime.startsWith('video/') || ['mp4', 'webm', 'ogg', 'mov'].includes(ext)) {
            body.className = 'modal-body p-3 text-center bg-dark';
            body.innerHTML = `<video src="${previewUrl}" controls autoplay class="w-100 rounded" style="max-height: 75vh;"></video>`;
        } else if (mime.startsWith('audio/') || ['mp3', 'wav', 'ogg', 'm4a'].includes(ext)) {
            body.className = 'modal-body p-5 text-center bg-dark d-flex align-items-center justify-content-center';
            body.innerHTML = `<audio src="${previewUrl}" controls class="w-75"></audio>`;
        } else if (['txt', 'csv', 'json', 'log', 'md', 'html', 'xml'].includes(ext)) {
            body.className = 'modal-body p-0 bg-light';
            body.innerHTML = `<iframe src="${previewUrl}" width="100%" height="650px" style="border:none; display:block; background:#fff;"></iframe>`;
        } else {
            body.className = 'modal-body p-5 text-center bg-light';
            body.innerHTML = `
                <div class="py-5">
                    <i class="bi bi-file-earmark-break text-muted" style="font-size: 4rem;"></i>
                    <h5 class="fw-bold mt-3">${filename}</h5>
                    <p class="text-muted">Direct preview is not supported for this file type in browser.</p>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <a href="${downloadUrl || previewUrl}" class="btn btn-primary">
                            <i class="bi bi-download me-1"></i> Download File
                        </a>
                        <a href="${previewUrl}" target="_blank" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Raw
                        </a>
                    </div>
                </div>
            `;
        }

        new bootstrap.Modal(document.getElementById('previewModal')).show();
    }

    // Open Version Modal
    function openVersionModal(fileId, fileName) {
        document.getElementById('versionFileName').innerText = fileName;
        document.getElementById('uploadVersionForm').action = appUrl('/files/' + fileId + '/versions');
        new bootstrap.Modal(document.getElementById('versionModal')).show();
    }

    // Detect ZIP files and alert user about Google Takeout extractor
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const files = fileInput.files;
            let hasZip = false;
            let zipName = '';
            for (let i = 0; i < files.length; i++) {
                if (files[i].name.toLowerCase().endsWith('.zip')) {
                    hasZip = true;
                    zipName = files[i].name;
                    break;
                }
            }
            const alertBox = document.getElementById('takeoutZipDetectedAlert');
            if (hasZip) {
                document.getElementById('detectedZipName').innerText = zipName;
                alertBox.classList.remove('d-none');
            } else {
                alertBox.classList.add('d-none');
            }
        });
    }

    // Resumable Chunked Uploader in File Manager (Supports 10MB to 50GB without server size limit errors)
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            const files = fileInput ? fileInput.files : null;
            if (!files || !files.length) return;

            e.preventDefault();
            const progressBar = document.getElementById('uploadProgressBar');
            const progressContainer = document.getElementById('uploadProgressContainer');
            const statusText = document.getElementById('uploadStatusText');
            const submitBtn = document.getElementById('startUploadBtn');
            const folderId = "{{ $currentFolder?->id }}";

            progressContainer.classList.remove('d-none');
            statusText.classList.remove('d-none');
            submitBtn.disabled = true;

            const CHUNK_SIZE = 4 * 1024 * 1024; // 4MB safe chunks
            let currentFileIndex = 0;

            function processFile(index) {
                if (index >= files.length) {
                    statusText.innerText = 'All file(s) uploaded successfully! Reloading...';
                    progressBar.style.width = '100%';
                    setTimeout(() => window.location.reload(), 1000);
                    return;
                }

                const file = files[index];
                const totalChunks = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
                const uploadId = 'up_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                let chunkIndex = 0;

                function uploadChunk() {
                    if (chunkIndex >= totalChunks) {
                        statusText.innerText = `Verified ${file.name}. Moving to next file...`;
                        processFile(index + 1);
                        return;
                    }

                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunkBlob = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('chunk', chunkBlob, `part_${chunkIndex}`);
                    formData.append('upload_id', uploadId);
                    formData.append('chunk_index', chunkIndex);
                    formData.append('total_chunks', totalChunks);
                    formData.append('file_size', file.size);
                    formData.append('original_filename', file.name);
                    if (folderId) {
                        formData.append('folder_id', folderId);
                    }

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '{{ route('files.chunk-upload') }}', true);
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    xhr.setRequestHeader('Accept', 'application/json');

                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            chunkIndex++;
                            const filePercent = Math.round((chunkIndex / totalChunks) * 100);
                            const overallPercent = Math.round(((index + (chunkIndex / totalChunks)) / files.length) * 100);

                            progressBar.style.width = overallPercent + '%';
                            const mbUploaded = (end / (1024 * 1024)).toFixed(1);
                            const mbTotal = (file.size / (1024 * 1024)).toFixed(1);
                            statusText.innerText = `[${index + 1}/${files.length}] ${file.name}: ${mbUploaded}MB / ${mbTotal}MB (${filePercent}%)`;

                            uploadChunk();
                        } else {
                            let errorMsg = 'Upload failed.';
                            try {
                                const res = JSON.parse(xhr.responseText);
                                errorMsg = res.message || res.error || errorMsg;
                            } catch (e) {
                                errorMsg = `Server error HTTP ${xhr.status}`;
                            }
                            alert(errorMsg);
                            submitBtn.disabled = false;
                        }
                    };

                    xhr.onerror = function() {
                        alert('Network connection interrupted during upload. Please retry.');
                        submitBtn.disabled = false;
                    };

                    xhr.send(formData);
                }

                uploadChunk();
            }

            processFile(0);
        });
    }

    function toggleFolderExpand(btn, count) {
        const icon = btn.querySelector('i');
        const label = btn.querySelector('.expand-label');
        setTimeout(() => {
            const isExpanded = btn.getAttribute('aria-expanded') === 'true';
            if (isExpanded) {
                icon.className = 'bi bi-chevron-up';
                label.innerText = 'Show less';
            } else {
                icon.className = 'bi bi-chevron-down';
                label.innerText = `+${count} more files`;
            }
        }, 50);
    }

    // Grid vs List View toggle for Folders
    function setDriveView(mode) {
        const gridView = document.getElementById('foldersGridView');
        const listView = document.getElementById('foldersListView');
        const btnGrid = document.getElementById('btnGridView');
        const btnList = document.getElementById('btnListView');

        if (!gridView || !listView) return;

        if (mode === 'list') {
            gridView.classList.add('d-none');
            listView.classList.remove('d-none');
            if (btnGrid) { btnGrid.classList.remove('active', 'btn-primary'); btnGrid.classList.add('btn-outline-secondary'); }
            if (btnList) { btnList.classList.add('active', 'btn-primary'); btnList.classList.remove('btn-outline-secondary'); }
            localStorage.setItem('drive_folders_view', 'list');
        } else {
            listView.classList.add('d-none');
            gridView.classList.remove('d-none');
            if (btnList) { btnList.classList.remove('active', 'btn-primary'); btnList.classList.add('btn-outline-secondary'); }
            if (btnGrid) { btnGrid.classList.add('active', 'btn-primary'); btnGrid.classList.remove('btn-outline-secondary'); }
            localStorage.setItem('drive_folders_view', 'grid');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const savedMode = localStorage.getItem('drive_folders_view');
        if (savedMode === 'list') {
            setDriveView('list');
        }
    });
</script>
@endsection
