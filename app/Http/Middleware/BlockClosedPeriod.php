<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\AccountingReportController;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * Refuses saves dated inside a closed fiscal year (Accounting › Year-end Closing).
 * Looks at the date field each form sends; reopening the year lifts the block.
 */
class BlockClosedPeriod
{
    public const DATE_FIELDS = [
        'sale_date', 'purchase_date', 'order_date', 'expense_date', 'bill_date', 'payment_date', 'journal_date',
        'transfer_date', 'run_date', 'issue_date', 'adjustment_date', 'txn_date', 'quotation_date', 'datetime', 'return_date',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('post') && auth()->check() && !$request->routeIs('admin.accounting.*', 'login', 'logout')) {
            foreach (self::DATE_FIELDS as $field) {
                $value = $request->input($field);
                if (!is_string($value) || $value === '') {
                    continue;
                }
                try {
                    $date = substr($value, 0, 10);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && AccountingReportController::isClosedDate($date)) {
                        $message = 'The books are closed for the year that includes ' . $date . '. Use a later date, or reopen that year in Accounting › Year-end Closing.';

                        return $request->expectsJson() || $request->ajax()
                            ? response()->json(['status' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422)
                            : back()->withErrors([$field => $message])->withInput();
                    }
                } catch (Throwable $e) {
                    // Before the fiscal_years table exists: nothing is closed.
                }
            }
        }

        return $next($request);
    }
}
