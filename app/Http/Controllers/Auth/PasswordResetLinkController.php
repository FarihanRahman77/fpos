<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Same answer whether or not the address belongs to an account (and whether it was throttled), so this form
        // cannot be used to find out which e-mail addresses have accounts. Inactive / deleted accounts get no link.
        $user = \App\Models\User::where('email', $request->input('email'))->first();
        if ($user && \App\Http\Controllers\Admin\BranchController::accountActive($user)) {
            try {
                Password::sendResetLink($request->only('email'));
            } catch (\Throwable $e) {
                report($e); // a mail failure must not reveal that the account exists
            }
        }

        return back()->with('status', 'If an account uses that e-mail address, a password reset link has been sent to it.');
    }
}
