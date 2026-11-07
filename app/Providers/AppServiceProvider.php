<?php

namespace App\Providers;

use App\Models\File;
use App\Models\Folder;
use App\Models\ImportJob;
use App\Models\User;
use App\Policies\FilePolicy;
use App\Policies\FolderPolicy;
use App\Policies\ImportJobPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ImportJob::class, ImportJobPolicy::class);

        // Safe fallback for session and cache drivers to prevent 500 crashes on shared hosting before database tables are created
        if (config('session.driver') === 'database') {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                    config(['session.driver' => 'file']);
                }
            } catch (\Throwable $e) {
                config(['session.driver' => 'file']);
            }
        }

        if (config('cache.default') === 'database') {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('cache')) {
                    config(['cache.default' => 'file']);
                }
            } catch (\Throwable $e) {
                config(['cache.default' => 'file']);
            }
        }
    }
}
