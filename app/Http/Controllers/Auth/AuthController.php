<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                try {
                    AuditLogService::logLogin($request->email, false, null, "Rate limit exceeded. Locked for {$seconds}s");
                } catch (\Throwable) {}
                return back()->withErrors([
                    'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
                ]);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($throttleKey, 300);
                try {
                    AuditLogService::logLogin($request->email, false, $user, 'Invalid credentials');
                } catch (\Throwable) {}
                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }

            if (!$user->isActive()) {
                try {
                    AuditLogService::logLogin($request->email, false, $user, 'Account deactivated');
                } catch (\Throwable) {}
                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Please contact an administrator.',
                ]);
            }

            RateLimiter::clear($throttleKey);

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            try {
                AuditLogService::logLogin($user->email, true, $user);
                AuditLogService::log('user.login', 'User', $user->id, ['ip' => $request->ip()]);
            } catch (\Throwable) {}

            return redirect()->intended(route('admin.dashboard'));
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->withErrors([
                'email' => 'Database connection error: ' . $e->getMessage() . ' - Please check database credentials in your Hostinger .env file.',
            ])->onlyInput('email');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            AuditLogService::log('user.logout', 'User', $user->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out securely.');
    }

    public function showProfile(): View
    {
        $user = Auth::user();
        return view('auth.profile', compact('user'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
        ]);

        AuditLogService::log('profile.updated', 'User', $user->id);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        AuditLogService::log('password.changed', 'User', $user->id);

        return back()->with('success', 'Password changed successfully.');
    }
}
