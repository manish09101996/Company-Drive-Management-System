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
    }
}
