<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\ImportJob;
use App\Models\User;
use App\Services\PrivateStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected PrivateStorageService $storageService;

    public function __construct(PrivateStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function index(): View
    {
        // 1. Basic Counts
        $totalFiles = File::count();
        $totalFolders = Folder::count();
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $inactiveUsers = $totalUsers - $activeUsers;

        // 2. Storage metrics
        $totalFileBytes = (int) File::sum('size_bytes');
        $formattedStorageUsed = $this->storageService->formatBytes($totalFileBytes);

        $diskStats = $this->storageService->getStorageStats();

        // 3. Recent Files & Activity
        $recentFiles = File::with(['uploader', 'folder'])
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get();

        $recentLogs = AuditLog::with('user')
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        // 4. Import stats
        $activeImports = ImportJob::whereIn('status', ['validating', 'manifest_ready', 'processing'])
            ->orderBy('id', 'desc')
            ->get();

        $recentImports = ImportJob::orderBy('id', 'desc')->limit(4)->get();
        $importWarning = ImportJob::where('status', 'failed')
            ->orWhere(function ($q) {
                $q->whereNotNull('error_summary')->where('error_summary', 'like', '%Warning%');
            })
            ->latest()
            ->first();

        // 5. Storage category breakdown
        $fileTypes = [
            'Documents' => File::whereIn('extension', ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx'])->sum('size_bytes'),
            'Images' => File::whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])->sum('size_bytes'),
            'Media' => File::whereIn('extension', ['mp4', 'mov', 'mp3', 'wav', 'avi'])->sum('size_bytes'),
            'Archives' => File::whereIn('extension', ['zip', 'rar', 'tar', 'gz', '7z'])->sum('size_bytes'),
        ];
        $fileTypes['Other'] = max(0, $totalFileBytes - array_sum($fileTypes));

        return view('admin.dashboard', compact(
            'totalFiles',
            'totalFolders',
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'totalFileBytes',
            'formattedStorageUsed',
            'diskStats',
            'recentFiles',
            'recentLogs',
            'activeImports',
            'recentImports',
            'importWarning',
            'fileTypes'
        ));
    }
}
