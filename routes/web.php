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
| Database Diagnostic & Connection Tester (For Shared Hosting Troubleshooting)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'POST'], '/db-status', function (\Illuminate\Http\Request $request) {
    if ($request->query('key') !== 'ArkleDrive2026' && $request->input('key') !== 'ArkleDrive2026') {
        abort(403, 'Unauthorized. Please provide ?key=ArkleDrive2026');
    }

    $message = '';
    $connected = false;
    $tables = [];

    // Check if form was submitted with test credentials
    if ($request->isMethod('POST')) {
        $host = trim($request->input('host', 'localhost'));
        $port = trim($request->input('port', '3306'));
        $database = trim($request->input('database'));
        $username = trim($request->input('username'));
        $password = (string) $request->input('password');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $connected = true;
            $message = "<div style='background:#065f46;color:#a7f3d0;padding:16px;border-radius:6px;margin-bottom:20px;font-weight:bold;'>
                &#10004; CONNECTION SUCCESSFUL! Connected to database '{$database}' as user '{$username}'.
            </div>";

            // If user opted to write to .env
            if ($request->has('save_env')) {
                $envPath = base_path('.env');
                if (file_exists($envPath)) {
                    $envContent = file_get_contents($envPath);
                    $replacements = [
                        '/^DB_HOST=.*$/m' => "DB_HOST={$host}",
                        '/^DB_PORT=.*$/m' => "DB_PORT={$port}",
                        '/^DB_DATABASE=.*$/m' => "DB_DATABASE={$database}",
                        '/^DB_USERNAME=.*$/m' => "DB_USERNAME={$username}",
                        '/^DB_PASSWORD=.*$/m' => 'DB_PASSWORD="' . addcslashes($password, '"\\$') . '"',
                    ];
                    $envContent = preg_replace(array_keys($replacements), array_values($replacements), $envContent);
                    file_put_contents($envPath, $envContent);
                    try {
                        \Illuminate\Support\Facades\Artisan::call('config:clear');
                    } catch (\Throwable) {}
                    $message .= "<div style='background:#1e3a8a;color:#bfdbfe;padding:12px;border-radius:6px;margin-bottom:20px;'>
                        &#10004; Saved credentials to .env file!
                    </div>";
                }
            }
        } catch (\Throwable $e) {
            $message = "<div style='background:#7f1d1d;color:#fca5a5;padding:16px;border-radius:6px;margin-bottom:20px;'>
                &#10008; CONNECTION FAILED: " . htmlspecialchars($e->getMessage()) . "
            </div>";
        }
    } else {
        // GET: Test current Laravel config
        $host = config('database.connections.mysql.host', 'localhost');
        $port = config('database.connections.mysql.port', '3306');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $connected = true;
            $message = "<div style='background:#065f46;color:#a7f3d0;padding:16px;border-radius:6px;margin-bottom:20px;font-weight:bold;'>
                &#10004; CURRENT .ENV CREDENTIALS CONNECTED SUCCESSFULLY!
            </div>";
        } catch (\Throwable $e) {
            $message = "<div style='background:#7f1d1d;color:#fca5a5;padding:16px;border-radius:6px;margin-bottom:20px;'>
                &#10008; CURRENT .ENV CONNECTION FAILED:<br><br><strong>" . htmlspecialchars($e->getMessage()) . "</strong>
                <br><br>
                <strong>Hostinger Troubleshooting:</strong>
                <ul style='margin:8px 0 0 20px;line-height:1.6;'>
                    <li>Check if the database user password matches what you set in Hostinger hPanel &rarr; Databases.</li>
                    <li>If you forgot the database password in Hostinger, click <strong>...</strong> (three dots) next to the database &rarr; <strong>Change Password</strong>, then test it below.</li>
                    <li>Make sure <strong>DB_HOST=localhost</strong> in Hostinger.</li>
                </ul>
            </div>";
        }
    }

    $passLen = strlen((string)$password);
    $maskedPass = $passLen > 2 ? substr($password, 0, 1) . str_repeat('*', max(1, $passLen - 2)) . substr($password, -1) : str_repeat('*', $passLen);

    $tablesHtml = '';
    if ($connected) {
        $tablesHtml = "<div style='margin-top:20px;padding:16px;background:#1e293b;border-radius:6px;'>
            <div style='color:#38bdf8;font-weight:bold;margin-bottom:8px;'>Database Tables (" . count($tables) . " found):</div>";
        if (empty($tables)) {
            $tablesHtml .= "<p style='color:#facc15;'>No tables found yet. Click below to run migrations & seeds!</p>";
        } else {
            $tablesHtml .= "<div style='display:flex;flex-wrap:wrap;gap:8px;'>";
            foreach ($tables as $t) {
                $tablesHtml .= "<span style='background:#334155;color:#e2e8f0;padding:4px 8px;border-radius:4px;font-size:12px;'>" . htmlspecialchars($t) . "</span>";
            }
            $tablesHtml .= "</div>";
        }
        $tablesHtml .= "<div style='margin-top:16px;display:flex;gap:12px;'>
            <a href='/migrate-db?key=ArkleDrive2026' style='background:#2563eb;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;font-weight:bold;'>Run Migrations & Seeds &rarr;</a>
            <a href='/login' style='background:#10b981;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;font-weight:bold;'>Go to Login &rarr;</a>
        </div></div>";
    }

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Database Diagnostic - Arkle Homes Drive</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 24px; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; background: #1e293b; padding: 28px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        h1 { margin-top: 0; color: #38bdf8; font-size: 22px; border-bottom: 1px solid #334155; padding-bottom: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        label { font-size: 13px; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 4px; }
        input[type="text"], input[type="password"] { width: 100%; box-sizing: border-box; padding: 10px 12px; background: #0f172a; border: 1px solid #475569; color: #fff; border-radius: 6px; font-size: 14px; }
        button { background: #38bdf8; color: #0f172a; border: 0; padding: 12px 24px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px; }
        button:hover { background: #7dd3fc; }
        .card { background: #0f172a; padding: 14px; border-radius: 6px; border: 1px solid #334155; margin-bottom: 20px; font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <h1>&#128279; Arkle Homes Drive &bull; Database Diagnostic & Connection Tool</h1>

    <div class="card">
        <div style="color:#94a3b8;margin-bottom:6px;">Current Configuration read by Laravel:</div>
        <div>DB_HOST: <strong style="color:#f8fafc;">{$host}</strong></div>
        <div>DB_PORT: <strong style="color:#f8fafc;">{$port}</strong></div>
        <div>DB_DATABASE: <strong style="color:#f8fafc;">{$database}</strong></div>
        <div>DB_USERNAME: <strong style="color:#f8fafc;">{$username}</strong></div>
        <div>DB_PASSWORD: <strong style="color:#f8fafc;">{$maskedPass} ({$passLen} characters)</strong></div>
    </div>

    {$message}
    {$tablesHtml}

    <h2 style="font-size:16px;color:#e2e8f0;margin-top:28px;">Test or Update MySQL Credentials</h2>
    <form method="POST" action="/db-status">
        <input type="hidden" name="key" value="ArkleDrive2026">
        <div class="grid">
            <div>
                <label>Database Host</label>
                <input type="text" name="host" value="{$host}" required>
            </div>
            <div>
                <label>Port</label>
                <input type="text" name="port" value="{$port}" required>
            </div>
        </div>
        <div class="grid">
            <div>
                <label>Database Name (e.g. 371849684_companyDrive)</label>
                <input type="text" name="database" value="{$database}" required>
            </div>
            <div>
                <label>Database Username</label>
                <input type="text" name="username" value="{$username}" required>
            </div>
        </div>
        <div style="margin-bottom:16px;">
            <label>Database Password (from Hostinger hPanel &rarr; Databases)</label>
            <input type="password" name="password" placeholder="Enter Hostinger database password" required>
        </div>
        <div style="margin-bottom:18px;">
            <label style="display:flex;align-items:center;gap:8px;color:#cbd5e1;cursor:pointer;">
                <input type="checkbox" name="save_env" value="1" checked style="width:18px;height:18px;">
                Save tested credentials to Hostinger <code>.env</code> file automatically upon successful test
            </label>
        </div>
        <button type="submit">Test Connection & Save</button>
    </form>
</div>
</body>
</html>
HTML;

    return response($html);
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
]);

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
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
]);

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
