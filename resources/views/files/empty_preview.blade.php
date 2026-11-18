<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empty File - {{ $file->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 24px;
        }
        .empty-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            max-width: 540px;
            width: 100%;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>
    <div class="empty-card">
        <div class="mb-3">
            <i class="bi bi-file-earmark-x text-warning" style="font-size: 4rem;"></i>
        </div>
        <h4 class="fw-bold text-white mb-2 text-break">{{ $file->name }}</h4>
        <div class="mb-3">
            <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-3 py-2">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Empty Document (0 Bytes)
            </span>
        </div>
        <p class="text-secondary small mb-4">
            This document contains 0 bytes of data and cannot be displayed in the document viewer.
            This typically occurs when a file was uploaded with 0 bytes, or when a Google Drive export contains a shortcut or placeholder.
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="{{ $downloadUrl }}" class="btn btn-outline-light btn-sm px-3" download>
                <i class="bi bi-download me-1"></i> Download File (0 B)
            </a>
            @if($file->folder_id)
            <a href="{{ route('drive.index', $file->folder?->uuid) }}" class="btn btn-primary btn-sm px-3">
                <i class="bi bi-folder me-1"></i> Open in Folder
            </a>
            @endif
        </div>
    </div>
</body>
</html>
