@extends('layouts.app')

@section('title', 'Departments')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-diagram-3 text-primary me-2"></i>Departments Management</h4>
        <p class="text-muted small mb-0">Organize company files and user access by business units and departments.</p>
    </div>
    <div>
        <button class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#newDeptModal">
            <i class="bi bi-plus-circle"></i> New Department
        </button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Users</th>
                    <th>Folders</th>
                    <th>Files</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                <tr>
                    <td class="fw-bold font-monospace text-primary">{{ $dept->code }}</td>
                    <td class="fw-semibold text-dark">{{ $dept->name }}</td>
                    <td class="small text-muted">{{ $dept->description ?? '-' }}</td>
                    <td class="small">{{ $dept->users_count }}</td>
                    <td class="small">{{ $dept->folders_count }}</td>
                    <td class="small">{{ $dept->files_count }}</td>
                    <td>
                        @if($dept->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-light border" onclick="openEditDeptModal({{ $dept->id }}, '{{ addslashes($dept->name) }}', '{{ $dept->code }}', '{{ addslashes($dept->description ?? '') }}', {{ $dept->is_active ? '1' : '0' }})">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted small">No departments configured yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- New Department Modal -->
<div class="modal fade" id="newDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('departments.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>New Department</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Department Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Legal & Compliance">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Short Code</label>
                    <input type="text" name="code" class="form-control" required placeholder="e.g. LGL" style="text-transform: uppercase;">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Overview of responsibilities..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editDeptForm" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil text-primary me-2"></i>Edit Department</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Department Name</label>
                    <input type="text" id="editDeptName" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Short Code</label>
                    <input type="text" id="editDeptCode" name="code" class="form-control" required style="text-transform: uppercase;">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea id="editDeptDesc" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select id="editDeptActive" name="is_active" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Department</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openEditDeptModal(id, name, code, desc, isActive) {
        document.getElementById('editDeptForm').action = appUrl('/departments/' + id);
        document.getElementById('editDeptName').value = name;
        document.getElementById('editDeptCode').value = code;
        document.getElementById('editDeptDesc').value = desc;
        document.getElementById('editDeptActive').value = isActive ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editDeptModal')).show();
    }
</script>
@endsection
