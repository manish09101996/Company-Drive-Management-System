<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Record an audit log entry.
     */
    public static function log(
        string $action,
        string $resourceType = null,
        ?int $resourceId = null,
        array $details = [],
        ?User $user = null
    ): AuditLog {
        $user = $user ?? Auth::user();
        $request = request();

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $request?->ip() ?? '127.0.0.1',
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 500) : null,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    /**
     * Record a login attempt event.
     */
    public static function logLogin(
        string $email,
        bool $success,
        ?User $user = null,
        ?string $failureReason = null
    ): LoginEvent {
        $request = request();

        return LoginEvent::create([
            'user_id' => $user?->id,
            'email' => $email,
            'status' => $success ? 'success' : 'failed',
            'ip_address' => $request?->ip() ?? '127.0.0.1',
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 500) : null,
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }
}
