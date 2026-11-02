<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Payslips of processed salary (payroll_run_items of posted runs): one PDF per employee and month,
 * all of a month in one PDF (a page per employee), and emailing each employee their own PDF.
 */
class PayslipController extends Controller
{
    use VoucherHelperTrait;

    public function index()
    {
        return view('admin.payroll.payslips.index', [
            'departments' => DB::table('departments')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function list(Request $request)
    {
        $month = $this->month($request->query('month'));
        $items = self::items($month, $request->filled('department_id') ? (int) $request->query('department_id') : null);

        return response()->json([
            'status' => true,
            'month'  => $month->format('F Y'),
            'data'   => $items->map(fn ($i) => [
                'id'          => $i->id,
                'code'        => $i->employee_code,
                'name'        => $i->name,
                'designation' => $i->designation,
                'department'  => $i->department,
                'email'       => $i->email,
                'run_no'      => $i->run_no,
                'net'         => (float) $i->net_payable,
                'paid'        => (float) $i->paid_amount,
                'due'         => round((float) $i->net_payable - (float) $i->paid_amount, 2),
            ])->values(),
            'totals' => [
                'net'  => round($items->sum('net_payable'), 2),
                'paid' => round($items->sum('paid_amount'), 2),
                'with_email' => $items->filter(fn ($i) => filter_var($i->email, FILTER_VALIDATE_EMAIL))->count(),
            ],
        ]);
    }

    /** One payslip; ?download=1 downloads, otherwise opens in the browser (print from there). */
    public function pdf(Request $request, int $id)
    {
        $slip = self::slip($id);
        abort_unless($slip, 404, 'Payslip not found.');

        $pdf = self::render([$slip]);
        $name = self::fileName($slip);

        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }

    /** Every payslip of the month (and department) in one PDF. */
    public function bulkPdf(Request $request)
    {
        $month = $this->month($request->query('month'));
        $ids = self::items($month, $request->filled('department_id') ? (int) $request->query('department_id') : null)->pluck('id');
        abort_if($ids->isEmpty(), 404, 'No processed salary in ' . $month->format('F Y') . '.');

        $pdf = self::render($ids->map(fn ($id) => self::slip($id))->filter()->values()->all());
        $name = 'Payslips-' . $month->format('Y-m') . '.pdf';

        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }

    public function email(int $id)
    {
        $slip = self::slip($id);
        if (!$slip) {
            return response()->json(['status' => false, 'message' => 'Payslip not found.'], 404);
        }

        $r = self::send($slip);

        return response()->json($r, $r['status'] ? 200 : 422);
    }

    /** Emails every employee of the month (and department) who has an email; reports each one. */
    public function emailBulk(Request $request)
    {
        $month = $this->month($request->input('month'));
        $items = self::items($month, $request->filled('department_id') ? (int) $request->input('department_id') : null);
        if ($request->filled('ids')) {
            $only = array_map('intval', (array) $request->input('ids'));
            $items = $items->whereIn('id', $only);
        }

        $results = [];
        foreach ($items as $item) {
            $slip = self::slip($item->id);
            $results[] = ['id' => $item->id, 'name' => $item->name, 'email' => $item->email] + ($slip ? self::send($slip) : ['status' => false, 'message' => 'Not found.']);
        }
        $sent = collect($results)->where('status', true)->count();

        return response()->json([
            'status'  => $sent > 0,
            'message' => $sent . ' of ' . count($results) . ' payslip(s) emailed.' . ($sent < count($results) ? ' See the list for the ones that failed.' : ''),
            'results' => $results,
        ], $sent > 0 || !$results ? 200 : 422);
    }

    /* ------------------------------------------------------------------ */
    /*  Shared                                                             */
    /* ------------------------------------------------------------------ */

    /** Processed salary lines (posted runs) of a month. */
    public static function items(Carbon $month, ?int $departmentId = null)
    {
        return BranchController::scope(DB::table('payroll_run_items as i'), 'e.branch_id')
            ->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->join('employees as e', 'e.id', '=', 'i.employee_id')
            ->where('r.month', $month->copy()->startOfMonth()->toDateString())->where('r.run_status', 'Posted')
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->orderByRaw('i.department IS NULL')->orderBy('i.department')->orderBy('e.employee_code')
            ->get(['i.id', 'i.net_payable', 'i.paid_amount', 'i.designation', 'i.department', 'r.run_no', 'e.employee_code', 'e.name', 'e.email']);
    }

    /** Everything a payslip shows: the frozen run line, its JSON details as line items and the payments. */
    public static function slip(int $itemId): ?array
    {
        $item = BranchController::scope(DB::table('payroll_run_items as i'), 'e.branch_id')
            ->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->join('employees as e', 'e.id', '=', 'i.employee_id')
            ->where('i.id', $itemId)->where('r.run_status', 'Posted')
            ->first(['i.*', 'r.run_no', 'r.month', 'r.run_date',
                'e.employee_code', 'e.name', 'e.email', 'e.phone', 'e.joining_date', 'e.salary_payment_mode', 'e.bank_name', 'e.bank_branch',
                'e.bank_account_no', 'e.mobile_banking_provider', 'e.mobile_banking_no', 'e.transport_allowance', 'e.other_allowance']);
        if (!$item) {
            return null;
        }

        $f = fn ($v) => round((float) $v, 2);
        $earnings = [['Basic Salary', $f($item->basic_salary), null]];
        foreach ([['House Rent', $item->house_rent], ['Medical Allowance', $item->medical_allowance], ['Compensation', $item->compensation]] as [$label, $v]) {
            if ((float) $v > 0) {
                $earnings[] = [$label, $f($v), null];
            }
        }
        $other = round((float) $item->gross_salary - (float) $item->basic_salary - (float) $item->house_rent - (float) $item->medical_allowance - (float) $item->compensation, 2);
        if ($other > 0.004) {
            $earnings[] = ['Other Allowances', $other, null];
        }
        foreach (json_decode($item->bonus_detail ?: '[]', true) ?: [] as $b) {
            $earnings[] = [$b['name'] ?? 'Bonus', $f($b['amount'] ?? 0), $b['rule'] ?? null];
        }
        foreach (json_decode($item->additions_detail ?: '[]', true) ?: [] as $a) {
            $earnings[] = [$a['name'] ?? 'Addition', $f($a['amount'] ?? 0), $a['remarks'] ?? null];
        }

        $deductions = [];
        foreach (json_decode($item->deductions_detail ?: '[]', true) ?: [] as $d) {
            $deductions[] = [$d['name'] ?? 'Deduction', $f($d['amount'] ?? 0), $d['remarks'] ?? null];
        }
        if ((float) $item->lwp_deduction > 0) {
            $deductions[] = ['Leave Without Pay (' . rtrim(rtrim(number_format((float) $item->lwp_days, 1), '0'), '.') . ' day(s))', $f($item->lwp_deduction), null];
        }
        if ((float) $item->pf_amount > 0) {
            $deductions[] = ['Provident Fund', $f($item->pf_amount), null];
        }
        foreach (json_decode($item->loan_detail ?: '[]', true) ?: [] as $l) {
            $deductions[] = [($l['type'] ?? 'Loan') . ' installment', $f($l['amount'] ?? 0), $l['loan_no'] ?? null];
        }

        $payments = DB::table('salary_payment_details as d')
            ->join('salary_payments as p', 'p.id', '=', 'd.salary_payment_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'p.payment_account_id')
            ->where('d.payroll_run_item_id', $item->id)->where('p.payment_status', 'Posted')
            ->orderBy('p.payment_date')->orderBy('p.id')
            ->get(['p.payment_no', 'p.payment_date', 'a.account_name', 'd.amount']);

        $paid = round($payments->sum('amount'), 2);
        $net = $f($item->net_payable);

        return [
            'item'        => $item,
            'month'       => Carbon::parse($item->month),
            'earnings'    => $earnings,
            'deductions'  => $deductions,
            'total_earn'  => round(array_sum(array_column($earnings, 1)), 2),
            'total_ded'   => round(array_sum(array_column($deductions, 1)), 2),
            'net'         => $net,
            'net_words'   => amount_in_words($net),
            'payments'    => $payments,
            'paid'        => $paid,
            'due'         => round(max(0, $net - $paid), 2),
            'attendance'  => self::attendance($item->employee_id, Carbon::parse($item->month)),
        ];
    }

    /** Attendance counts of the month for the payslip, or null when no attendance was taken. */
    private static function attendance(int $employeeId, Carbon $month): ?array
    {
        try {
            $s = AttendanceController::monthSummary($month, null, [$employeeId]);
            $c = $s['employees'][0]['counts'] ?? null;
            if (!$c || ($c['attended'] + $c['absent'] + $c['leave']) === 0) {
                return null;
            }

            return $c;
        } catch (Throwable) {
            return null;
        }
    }

    public static function render(array $slips)
    {
        return PDF::loadView('admin.payroll.payslips.pdf', ['slips' => $slips])->setPaper('a4');
    }

    public static function fileName(array $slip): string
    {
        return 'Payslip-' . preg_replace('/[^A-Za-z0-9-]+/', '', (string) $slip['item']->employee_code) . '-' . $slip['month']->format('Y-m') . '.pdf';
    }

    /** Emails one payslip; ['status' => bool, 'message' => string]. */
    public static function send(array $slip): array
    {
        $item = $slip['item'];
        if (!filter_var($item->email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => $item->name . ' has no valid email address.'];
        }

        $company = DB::table('settings')->where('deleted', 'No')->value('company_name') ?: config('app.name');
        try {
            $pdf = self::render([$slip])->output();
            $html = view('admin.payroll.payslips.email', ['slip' => $slip, 'company' => $company])->render();

            Mail::html($html, function ($m) use ($item, $slip, $pdf, $company) {
                $m->to($item->email, $item->name)
                    ->subject('Payslip for ' . $slip['month']->format('F Y') . ' — ' . $company)
                    ->attachData($pdf, self::fileName($slip), ['mime' => 'application/pdf']);
            });

            return ['status' => true, 'message' => 'Payslip emailed to ' . $item->email . '.'];
        } catch (Throwable $e) {
            Log::error('Payslip email error', ['item' => $item->id, 'error' => $e->getMessage()]);

            return ['status' => false, 'message' => 'Could not email ' . $item->name . ': ' . mb_strimwidth($e->getMessage(), 0, 160, '…')];
        }
    }

    private function month(?string $value): Carbon
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $value) ? Carbon::createFromFormat('!Y-m', $value) : now()->startOfMonth();
    }
}
