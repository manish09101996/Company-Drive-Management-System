<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Files\FileController;
use App\Http\Controllers\Files\FolderController;
use App\Http\Controllers\Files\RecycleBinController;
use App\Http\Controllers\Files\ShareController;
use App\Http\Controllers\Imports\TakeoutImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| One-time Web Migration Helper (For Shared Hosting without SSH access)
|--------------------------------------------------------------------------
*/
Route::get('/migrate-db', function (\Illuminate\Http\Request $request) {
    if ($request->query('key') !== 'ArkleDrive2026') {
        abort(403, 'Unauthorized. Please provide ?key=ArkleDrive2026');
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrateOut = \Illuminate\Support\Facades\Artisan::output();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $seedOut = \Illuminate\Support\Facades\Artisan::output();

        return response('<pre style="background:#0f172a;color:#10b981;padding:24px;border-radius:8px;font-family:monospace;font-size:14px;line-height:1.5;">'
            . "=============================================\n"
            . "  ARKLE HOMES DRIVE - DATABASE SETUP SUCCESS\n"
            . "=============================================\n\n"
            . e($migrateOut)
            . "\n" . e($seedOut)
            . "\n\n<a href='/login' style='color:#38bdf8;font-weight:bold;font-size:16px;text-decoration:underline;'>&rarr; Click here to Login to Arkle Homes Drive</a>"
            . '</pre>');
    } catch (\Throwable $e) {
        return response('<pre style="background:#0f172a;color:#f87171;padding:24px;border-radius:8px;font-family:monospace;font-size:14px;line-height:1.5;">'
            . "DATABASE SETUP ERROR:\n" . e($e->getMessage())
            . "\n\nPlease check your DB credentials in .env and make sure DB_HOST=localhost"
            . '</pre>', 500);
    }
})->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class]);

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');

    /*
    |--------------------------------------------------------------------------
    | Dashboard & Admin
    |--------------------------------------------------------------------------
    */
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // User management
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    // Role management
    Route::resource('roles', RoleController::class)->only(['index', 'edit', 'update']);

    // Department management
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update']);

    // Audit logs & security
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/login-events', [AuditLogController::class, 'loginEvents'])->name('audit.logins');

    /*
    |--------------------------------------------------------------------------
    | File & Folder Management
    |--------------------------------------------------------------------------
    */
    Route::get('/drive/{uuid?}', [FolderController::class, 'index'])->name('drive.index');
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::post('/folders/{folder}/move', [FolderController::class, 'move'])->name('folders.move');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');

    Route::post('/files/upload', [FileController::class, 'store'])->name('files.upload');
    Route::post('/files/chunk-upload', [FileController::class, 'chunkUpload'])->name('files.chunk-upload');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}/preview', [FileController::class, 'preview'])->name('files.preview');
    Route::put('/files/{file}', [FileController::class, 'update'])->name('files.update');
    Route::post('/files/{file}/move', [FileController::class, 'move'])->name('files.move');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/{file}/versions', [FileController::class, 'uploadVersion'])->name('files.versions.upload');
    Route::post('/files/{file}/versions/{version}/restore', [FileController::class, 'restoreVersion'])->name('files.versions.restore');
    Route::post('/files/{file}/favorite', [FileController::class, 'toggleFavorite'])->name('files.favorite');
    Route::post('/files/{file}/extract', [FileController::class, 'extractZip'])->name('files.extract');

    // Item-level permissions & sharing
    Route::get('/share/permissions', [ShareController::class, 'getPermissions'])->name('share.permissions');
    Route::post('/share/permissions', [ShareController::class, 'updatePermission'])->name('share.update');
    Route::delete('/share/permissions', [ShareController::class, 'revokePermission'])->name('share.revoke');

    // Recycle bin
    Route::get('/recycle-bin', [RecycleBinController::class, 'index'])->name('recycle-bin.index');
    Route::post('/recycle-bin/{item}/restore', [RecycleBinController::class, 'restore'])->name('recycle-bin.restore');
    Route::delete('/recycle-bin/{item}/force', [RecycleBinController::class, 'forceDelete'])->name('recycle-bin.force');
    Route::post('/recycle-bin/empty', [RecycleBinController::class, 'empty'])->name('recycle-bin.empty');

    /*
    |--------------------------------------------------------------------------
    | Google Takeout ZIP Import Center
    |--------------------------------------------------------------------------
    */
    Route::get('/imports', [TakeoutImportController::class, 'index'])->name('imports.index');
    Route::post('/imports/chunk', [TakeoutImportController::class, 'uploadChunk'])->name('imports.chunk');
    Route::post('/imports/{job}/inspect', [TakeoutImportController::class, 'inspect'])->name('imports.inspect');
    Route::get('/imports/{uuid}', [TakeoutImportController::class, 'show'])->name('imports.show');
    Route::get('/imports/{job}/items', [TakeoutImportController::class, 'getItems'])->name('imports.items');
    Route::post('/imports/{job}/selection', [TakeoutImportController::class, 'updateSelection'])->name('imports.selection');
    Route::post('/imports/{job}/start', [TakeoutImportController::class, 'start'])->name('imports.start');
    Route::post('/imports/{job}/batch', [TakeoutImportController::class, 'processBatch'])->name('imports.batch');
    Route::post('/imports/{job}/retry', [TakeoutImportController::class, 'retryFailed'])->name('imports.retry');
    Route::post('/imports/{job}/cancel', [TakeoutImportController::class, 'cancel'])->name('imports.cancel');
    Route::delete('/imports/{job}', [TakeoutImportController::class, 'destroy'])->name('imports.destroy');
});
