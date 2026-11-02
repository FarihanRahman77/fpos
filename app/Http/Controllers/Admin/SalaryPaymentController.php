<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\AccountBalanceTrait;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Paying processed salary (PayrollRunController). Any amount per employee up to what is still due,
 * from any cash / bank / mobile banking account, as many times as needed.
 *
 * Posting: Dr salary payable (the run's ledger) / Cr cash-bank account. Cancelling reverses it.
 */
class SalaryPaymentController extends Controller
{
    use AccountBalanceTrait, VoucherHelperTrait;

    public function index()
    {
        return view('admin.payroll.salary-payments.index');
    }

    public function list(Request $request)
    {
        try {
            $rows = BranchController::scope(DB::table('salary_payments as p'), 'p.branch_id')
                ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'p.payment_account_id')
                ->leftJoin('chart_of_accounts as m', 'm.id', '=', 'a.parent_id')
                ->leftJoin('payroll_runs as r', 'r.id', '=', 'p.payroll_run_id')
                ->when($request->filled('year'), fn ($q) => $q->whereYear('p.month', (int) $request->query('year')))
                ->orderByDesc('p.payment_date')->orderByDesc('p.id')
                ->get(['p.*', 'a.account_name as account', 'm.account_name as method', 'r.run_no']);

            return response()->json([
                'data' => $rows->values()->map(function ($r, $i) {
                    $id = (int) $r->id;
                    $items = [['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => "SalaryPay.view($id)"]];
                    if ($r->payment_status === 'Posted') {
                        $items[] = ['divider' => true];
                        $items[] = ['label' => 'Cancel payment', 'icon' => 'fas fa-ban', 'class' => 'text-danger', 'onclick' => "SalaryPay.cancel($id)"];
                    }

                    return [
                        $i + 1,
                        '<span class="fw-semibold">' . e($r->payment_no) . '</span><div class="text-muted small">' . e($r->run_no) . '</div>',
                        '<span class="d-none">' . $r->month . '</span>' . e(Carbon::parse($r->month)->format('F Y')),
                        e(Carbon::parse($r->payment_date)->format('d M Y')),
                        e($r->account ?: '-') . ($r->method ? '<div class="text-muted small">' . e($r->method) . '</div>' : ''),
                        '<span class="d-block text-center">' . (int) $r->employee_count . '</span>',
                        '<span class="d-block text-end fw-semibold">' . number_format((float) $r->total_paid, 2) . '</span>',
                        $r->payment_status === 'Posted'
                            ? '<span class="badge bg-success">Posted</span>'
                            : '<span class="badge bg-secondary" title="' . e($r->cancel_reason) . '">Cancelled</span>',
                        action_dropdown($items),
                    ];
                }),
            ]);
        } catch (Throwable $e) {
            Log::error('Salary payment list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load salary payments.'], 500);
        }
    }

    public function show(int $id)
    {
        $payment = BranchController::scope(DB::table('salary_payments as p'), 'p.branch_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'p.payment_account_id')
            ->leftJoin('payroll_runs as r', 'r.id', '=', 'p.payroll_run_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.created_by')
            ->where('p.id', $id)
            ->first(['p.*', 'a.account_name as account', 'r.run_no', 'u.name as created_by_name']);

        if (!$payment) {
            return response()->json(['status' => false, 'message' => 'Salary payment not found.'], 404);
        }

        $details = DB::table('salary_payment_details as d')
            ->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->join('payroll_run_items as i', 'i.id', '=', 'd.payroll_run_item_id')
            ->where('d.salary_payment_id', $id)->orderBy('i.department')->orderBy('e.employee_code')
            ->get(['d.amount', 'e.name', 'e.employee_code', 'i.department', 'i.net_payable', 'i.paid_amount']);

        return response()->json(['status' => true, 'data' => $payment, 'details' => $details]);
    }

    /**
     * items: [payroll_run_item_id => amount]. Items of several runs (e.g. department-wise) become one
     * payment per run, all from the same account.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_date'       => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', $this->coaExists(3, 'Liquid Asset')],
            'items'              => ['required', 'array', 'min:1'],
            'items.*'            => ['nullable', 'numeric', 'min:0'],
            'remarks'            => ['nullable', 'string', 'max:500'],
        ], [
            'payment_account_id.required' => 'Select the cash / bank account the salary is paid from.',
            'items.required'              => 'Enter the amount to pay at least one employee.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $payments = DB::transaction(function () use ($request) {
                $wanted = collect($request->input('items'))->map(fn ($v) => round((float) $v, 2))->filter(fn ($v) => $v > 0);
                abort_if($wanted->isEmpty(), 422, 'Enter the amount to pay at least one employee.');

                $items = BranchController::scope(DB::table('payroll_run_items as i'), 'e.branch_id')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
                    ->join('employees as e', 'e.id', '=', 'i.employee_id')
                    ->whereIn('i.id', $wanted->keys()->all())->where('r.run_status', 'Posted')
                    ->lockForUpdate()->get(['i.*', 'r.month', 'r.payable_account_id', 'e.name']);

                abort_if($items->count() !== $wanted->count(), 422, 'Some employees are not in a processed salary any more. Reload the sheet.');

                foreach ($items as $it) {
                    $due = round((float) $it->net_payable - (float) $it->paid_amount, 2);
                    abort_if($wanted[$it->id] > $due + 0.004, 422, $it->name . ': only ' . number_format($due, 2) . ' is due.');
                }

                $created = [];
                foreach ($items->groupBy('payroll_run_id') as $runId => $runItems) {
                    $total = round($runItems->sum(fn ($it) => $wanted[$it->id]), 2);

                    $last = DB::table('salary_payments')->lockForUpdate()->max('payment_no');
                    $next = $last ? ((int) substr($last, 4)) + 1 : 1;

                    $paymentId = DB::table('salary_payments')->insertGetId([
                        'payment_no'         => 'SAL-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                        'payroll_run_id'     => $runId,
                        'month'              => $runItems->first()->month,
                        'payment_date'       => $request->input('payment_date'),
                        'payment_account_id' => (int) $request->input('payment_account_id'),
                        'employee_count'     => $runItems->count(),
                        'total_paid'         => $total,
                        'remarks'            => $this->nullIfBlank($request->input('remarks')),
                        'created_by'         => Auth::id(),
                        'created_date'       => now(),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    foreach ($runItems as $it) {
                        DB::table('salary_payment_details')->insert([
                            'salary_payment_id'   => $paymentId,
                            'payroll_run_item_id' => $it->id,
                            'employee_id'         => $it->employee_id,
                            'amount'              => $wanted[$it->id],
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);
                        DB::table('payroll_run_items')->where('id', $it->id)->increment('paid_amount', $wanted[$it->id]);
                    }
                    DB::table('payroll_runs')->where('id', $runId)->increment('total_paid', $total);

                    $this->postBalances([(int) $runItems->first()->payable_account_id => $total], [(int) $request->input('payment_account_id') => $total], 1);
                    $created[] = DB::table('salary_payments')->where('id', $paymentId)->first();
                }

                DB::table('payroll_settings')->update(['salary_payment_account_id' => (int) $request->input('payment_account_id')]);

                return collect($created);
            });

            return response()->json([
                'status'  => true,
                'message' => 'Salary paid (' . $payments->pluck('payment_no')->implode(', ') . '): '
                    . $payments->sum('employee_count') . ' employee(s), ' . number_format((float) $payments->sum('total_paid'), 2) . '.',
            ]);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Salary payment error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to post the salary payment. Please try again.'], 500);
        }
    }

    public function cancel(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), ['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => 'Write why the payment is cancelled.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $payment = DB::transaction(function () use ($request, $id) {
                $payment = BranchController::scope(DB::table('salary_payments'), 'branch_id')->where('id', $id)->lockForUpdate()->first();
                abort_unless($payment, 404, 'Salary payment not found.');
                abort_unless($payment->payment_status === 'Posted', 422, 'This payment is already cancelled.');

                $run = DB::table('payroll_runs')->where('id', $payment->payroll_run_id)->lockForUpdate()->first();

                foreach (DB::table('salary_payment_details')->where('salary_payment_id', $id)->get() as $d) {
                    DB::table('payroll_run_items')->where('id', $d->payroll_run_item_id)->decrement('paid_amount', (float) $d->amount);
                }
                DB::table('payroll_runs')->where('id', $run->id)->decrement('total_paid', (float) $payment->total_paid);

                $this->postBalances([(int) $run->payable_account_id => (float) $payment->total_paid], [(int) $payment->payment_account_id => (float) $payment->total_paid], -1);

                DB::table('salary_payments')->where('id', $id)->update([
                    'payment_status' => 'Cancelled',
                    'cancelled_by'   => Auth::id(),
                    'cancelled_at'   => now(),
                    'cancel_reason'  => trim($request->input('reason')),
                    'updated_at'     => now(),
                ]);

                return $payment;
            });

            return response()->json(['status' => true, 'message' => 'Salary ' . $payment->payment_no . ' cancelled. That amount is due again.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Salary payment cancel error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to cancel the payment.'], 500);
        }
    }
}
