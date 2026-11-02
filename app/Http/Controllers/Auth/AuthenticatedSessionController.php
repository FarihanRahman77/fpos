<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\Admin\Setting;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $setting = Setting::where('deleted', 'No')
        ->where('status', 'Active')
        ->first();
        return view('admin.auth.login', compact('setting'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // The branch chosen at login is the only branch this session sees; it cannot be changed until sign-out.
        $branchId = $request->chosenBranch();
        $request->session()->put(\App\Http\Controllers\Admin\BranchController::SESSION_KEY, $branchId);
        \Illuminate\Support\Facades\Cookie::queue('fpos_branch', (string) $branchId, 60 * 24 * 365);

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
