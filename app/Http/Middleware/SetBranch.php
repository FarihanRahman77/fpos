<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\BranchController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * Every signed-in request works in exactly one branch: the one chosen at login (session loggedInBranchId).
 * A session without it (e.g. restored from "remember me"), or whose branch was deactivated or taken away from
 * the user, is signed out so the user picks a branch on the login page again.
 * Also tells the database which branch new rows belong to (@fpos_branch, read by the branch_id triggers).
 */
class SetBranch
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        try {
            $branchId = BranchController::loggedInId();
            $allowed = $branchId && in_array($branchId, BranchController::allowedIds(), true);
        } catch (Throwable $e) {
            return $next($request); // before the branches migration has run
        }

        if (!$allowed) {
            $message = !BranchController::accountActive(Auth::user())
                ? 'Your account is inactive. Ask an administrator to activate it.'
                : ($branchId
                    ? 'You no longer have access to that branch. Sign in to a branch you are allowed in.'
                    : 'Choose your branch and sign in again.');
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['status' => false, 'message' => $message], 401)
                : redirect()->route('login')->withErrors(['branch_id' => $message]);
        }

        DB::statement('SET @fpos_branch = ?', [$branchId]);
        View::share('loggedInBranch', DB::table('branches')->where('id', $branchId)->first(['id', 'name', 'code']));

        return $next($request);
    }
}
