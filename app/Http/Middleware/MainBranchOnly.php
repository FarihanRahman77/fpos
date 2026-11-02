<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\BranchController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Company-wide screens (ledger balances, year-end closing, branch setup, company configuration) open only when
 * signed in to the main branch — every other login works with its own branch's data only.
 *
 * As a route middleware ('main.branch') it always applies. In the web group it runs as 'MainBranchOnly:listed'
 * and applies to the route names in HEAD_OFFICE (minus the ones every branch needs).
 */
class MainBranchOnly
{
    public const HEAD_OFFICE = [
        'admin.settings.*', 'admin.users.*', 'admin.roles.*', 'admin.permissions.*',
        'admin.system.backups*', 'admin.system.email-sms*', 'admin.system.activity.retention', 'admin.system.activity.prune', 'admin.payroll-setup.*', 'admin.customers.settings*',
    ];

    /** Used by everyday screens in every branch. */
    public const OPEN = ['admin.users.profile*', 'admin.payroll-setup.options'];

    public function handle(Request $request, Closure $next, string $mode = 'always')
    {
        if ($mode === 'listed') {
            $name = (string) optional($request->route())->getName();
            if (!$name || !Str::is(self::HEAD_OFFICE, $name) || Str::is(self::OPEN, $name) || !auth()->check()) {
                return $next($request);
            }
        }

        if (BranchController::isMainBranch()) {
            return $next($request);
        }

        $main = BranchController::name(BranchController::mainId());
        $message = 'This is company-wide, so it is only available when signed in to ' . $main . ' (head office).';

        return $request->expectsJson() || $request->ajax()
            ? response()->json(['status' => false, 'message' => $message], 403)
            : response()->view('admin.main-branch-only', ['message' => $message, 'mainBranch' => $main], 403);
    }
}
