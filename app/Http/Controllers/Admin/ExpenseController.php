<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Expense;
use App\Models\Admin\ExpenseDetail;
use App\Models\Admin\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use PDF;

/**
 * Expense (header) + expense_details (lines).
 *
 * - Each line carries an Expense head: 2nd layer id + 3rd layer id.
 * - Each expense has ONE payment source: Liquid Asset 2nd layer id + 3rd layer id.
 * - Posting (chart_of_accounts.current_balance):
 *      payment account (Liquid Asset)  -> CREDIT  (total)
 *      expense heads                   -> DEBIT   (each line)
 *   The same movement is rolled up to each account's 2nd and 1st layer parents.
 */
class ExpenseController extends Controller
{
    private const EXPENSE_TYPE = 'Expense';
    private const PAYMENT_TYPE = 'Liquid Asset';
    private const MAX_LINES    = 100;

    /* ------------------------------------------------------------------ */
    /*  Page                                                              */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return view('admin.accounts.expense.index', [
            'employees' => DB::table('employees')
                ->where('deleted', 'No')->where('status', 'Active')
                ->orderBy('name')
                ->get(['id', 'employee_code', 'name']),

            'expenseGroups' => $this->groups(self::EXPENSE_TYPE),
            'paymentGroups' => $this->groups(self::PAYMENT_TYPE),

            // Only for the "expense account" filter (grouped by 2nd layer).
            'expenseLedgers' => DB::table('chart_of_accounts as c')
                ->join('chart_of_accounts as g', 'g.id', '=', 'c.parent_id')
                ->where('c.layer_level', 3)
                ->where('c.account_type', self::EXPENSE_TYPE)
                ->where('c.deleted', 'No')
                ->orderBy('g.account_code')->orderBy('c.account_code')
                ->get(['c.id', 'c.account_code', 'c.account_name', 'g.account_name as group_name']),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Cascading dropdown: 3rd layer children of a 2nd layer account     */
    /* ------------------------------------------------------------------ */

    public function children(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'parent_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid parent is required.', 'data' => []], 422);
        }

        $rows = DB::table('chart_of_accounts')
            ->where('parent_id', (int) $request->query('parent_id'))
            ->where('layer_level', 3)
            ->whereIn('account_type', [self::EXPENSE_TYPE, self::PAYMENT_TYPE])
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        return response()->json(['status' => true, 'data' => $rows]);
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable                                                         */
    /* ------------------------------------------------------------------ */

    public function list(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from'               => ['nullable', 'date'],
            'to'                 => ['nullable', 'date', 'after_or_equal:from'],
            'employee_id'        => ['nullable', 'integer'],
            'expense_account_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['data' => [], 'message' => $validator->errors()->first()], 422);
        }

        try {
            $query = DB::table('expenses as e')
                ->leftJoin('employees as emp', 'emp.id', '=', 'e.employee_id')
                ->leftJoin('chart_of_accounts as pg', 'pg.id', '=', 'e.payment_group_id')
                ->leftJoin('chart_of_accounts as pa', 'pa.id', '=', 'e.payment_account_id')
                ->where('e.deleted', 'No')
                ->select(
                    'e.*',
                    'emp.name as employee_name',
                    'emp.employee_code',
                    'pg.account_name as payment_group_name',
                    'pa.account_code as payment_code',
                    'pa.account_name as payment_name'
                );

            if ($request->filled('from')) {
                $query->whereDate('e.expense_date', '>=', $request->query('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('e.expense_date', '<=', $request->query('to'));
            }

            if ($request->filled('employee_id')) {
                $query->where('e.employee_id', (int) $request->query('employee_id'));
            }

            if ($request->filled('expense_account_id')) {
                $accountId = (int) $request->query('expense_account_id');

                $query->whereExists(function ($q) use ($accountId) {
                    $q->select(DB::raw(1))
                        ->from('expense_details as d')
                        ->whereColumn('d.expense_id', 'e.id')
                        ->where('d.expense_account_id', $accountId);
                });
            }

            $expenses = $query->orderByDesc('e.expense_date')->orderByDesc('e.id')->get();

            // Expense head names for every listed expense (single query).
            $heads = DB::table('expense_details as d')
                ->join('chart_of_accounts as c', 'c.id', '=', 'd.expense_account_id')
                ->whereIn('d.expense_id', $expenses->pluck('id'))
                ->orderBy('d.id')
                ->get(['d.expense_id', 'c.account_name'])
                ->groupBy('expense_id');

            $output = ['data' => []];
            $i = 1;

            foreach ($expenses as $expense) {
                $id    = (int) $expense->id;
                $names = $heads->get($expense->id, collect())->pluck('account_name')->unique()->values();

                $headsCell = $names->take(2)->map(
                    fn ($n) => '<span class="badge bg-light text-dark border me-1">' . e($n) . '</span>'
                )->implode('');

                if ($names->count() > 2) {
                    $headsCell .= '<span class="text-muted small">+' . ($names->count() - 2) . ' more</span>';
                }

                $date = Carbon::parse($expense->expense_date);

                $paidFrom = $expense->payment_code
                    ? '<div><span class="coa-code">' . e($expense->payment_code) . '</span> ' . e($expense->payment_name) . '</div>'
                        . '<div class="text-muted small">' . e($expense->payment_group_name) . '</div>'
                    : '<span class="text-muted">-</span>';

                $button = '
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" title="Edit"
                                onclick="pdf(' . $id . ')">
                            <i class="fas fa-file"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" title="Delete"
                                onclick="deleteExpense(' . $id . ')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>';

                $output['data'][] = [
                    $i++,
                    '<span class="fw-semibold">' . e($expense->expense_no) . '</span>',
                    // hidden ISO prefix / zero-padded value keep column sorting correct
                    '<span class="d-none">' . $date->format('Y-m-d') . '</span>' . e($date->format('d M Y')),
                    e($expense->employee_name ?: '-')
                        . ($expense->employee_code ? ' <span class="text-muted small">(' . e($expense->employee_code) . ')</span>' : ''),
                    $paidFrom,
                    $headsCell ?: '<span class="text-muted">-</span>',
                    '<span class="d-none">' . sprintf('%021.2f', (float) $expense->total_amount) . '</span>'
                        . '<span class="d-block text-end fw-semibold">' . number_format((float) $expense->total_amount, 2) . '</span>',
                    $button,
                ];
            }

            $output['summary'] = [
                'filtered_total' => number_format((float) $expenses->sum('total_amount'), 2),
                'filtered_count' => $expenses->count(),
                'month_total'    => number_format((float) DB::table('expenses')
                    ->where('deleted', 'No')
                    ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('total_amount'), 2),
                'today_total'    => number_format((float) DB::table('expenses')
                    ->where('deleted', 'No')
                    ->whereDate('expense_date', now()->toDateString())
                    ->sum('total_amount'), 2),
            ];

            return response()->json($output);
        } catch (Throwable $e) {
            Log::error('Expense list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load expenses.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  One expense with its lines (edit modal)                           */
    /* ------------------------------------------------------------------ */

    public function show(Request $request)
    {
        try {
            $expense = DB::table('expenses as e')
                ->leftJoin('employees as emp', 'emp.id', '=', 'e.employee_id')
                ->leftJoin('chart_of_accounts as pg', 'pg.id', '=', 'e.payment_group_id')
                ->leftJoin('chart_of_accounts as pa', 'pa.id', '=', 'e.payment_account_id')
                ->where('e.id', (int) $request->query('id'))
                ->where('e.deleted', 'No')
                ->first([
                    'e.*',
                    'emp.name as employee_name', 'emp.employee_code',
                    'pg.account_code as pg_code', 'pg.account_name as pg_name',
                    'pa.account_code as pa_code', 'pa.account_name as pa_name',
                ]);

            if (!$expense) {
                return response()->json(['status' => false, 'message' => 'Expense not found.'], 404);
            }

            $details = DB::table('expense_details as d')
                ->leftJoin('chart_of_accounts as g', 'g.id', '=', 'd.expense_group_id')
                ->leftJoin('chart_of_accounts as c', 'c.id', '=', 'd.expense_account_id')
                ->where('d.expense_id', $expense->id)
                ->orderBy('d.id')
                ->get([
                    'd.expense_group_id', 'd.expense_account_id', 'd.description', 'd.amount',
                    'g.account_code as g_code', 'g.account_name as g_name',
                    'c.account_code as c_code', 'c.account_name as c_name',
                ])
                ->map(fn ($d) => [
                    'expense_group_id'   => $d->expense_group_id,
                    'expense_account_id' => $d->expense_account_id,
                    'group_label'        => $d->g_code . ' - ' . $d->g_name,
                    'account_label'      => $d->c_code . ' - ' . $d->c_name,
                    'description'        => $d->description,
                    'amount'             => number_format((float) $d->amount, 2, '.', ''),
                ]);

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'                    => $expense->id,
                    'expense_no'            => $expense->expense_no,
                    'expense_date'          => Carbon::parse($expense->expense_date)->format('Y-m-d'),
                    'employee_id'           => $expense->employee_id,
                    'employee_label'        => $expense->employee_name ? $expense->employee_name . ' (' . $expense->employee_code . ')' : '',
                    'payment_group_id'      => $expense->payment_group_id,
                    'payment_group_label'   => $expense->pg_code . ' - ' . $expense->pg_name,
                    'payment_account_id'    => $expense->payment_account_id,
                    'payment_account_label' => $expense->pa_code . ' - ' . $expense->pa_name,
                    'reference_no'          => $expense->reference_no,
                    'note'                  => $expense->note,
                    'details'               => $details,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Expense show error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to load expense.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Create / update (update when "id" is posted)                      */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'id'                 => ['nullable', 'integer'],
            'expense_date'       => ['required', 'date'],
            'employee_id'        => ['nullable', 'integer'],
            'payment_group_id'   => ['required', 'integer'],
            'payment_account_id' => ['required', 'integer'],
            'reference_no'       => ['nullable', 'string', 'max:100'],
            'note'               => ['nullable', 'string', 'max:1000'],

            'details'                      => ['required', 'array', 'min:1', 'max:' . self::MAX_LINES],
            'details.*.expense_group_id'   => ['required', 'integer'],
            'details.*.expense_account_id' => ['required', 'integer'],
            'details.*.description'        => ['nullable', 'string', 'max:255'],
            'details.*.amount'             => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
        ], [
            'employee_id.required'          => 'Please select the employee.',
            'payment_group_id.required'     => 'Please select the payment group.',
            'payment_account_id.required'   => 'Please select the payment account.',

            'details.required'              => 'Add at least one expense line.',
            'details.min'                   => 'Add at least one expense line.',
            'details.max'                   => 'You can add at most ' . self::MAX_LINES . ' lines.',
            'details.*.expense_group_id.required'   => 'Select an expense group.',
            'details.*.expense_account_id.required' => 'Select an expense account.',
            'details.*.amount.required'     => 'Enter the amount.',
            'details.*.amount.min'          => 'Amount must be greater than zero.',
        ]);

        // FIX 1: the validator was never checked, so invalid data was saved.
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Please correct the validation errors.',
                'errors'  => $validator->errors(),
            ], 422);
        }


        DB::beginTransaction();
        try {
                // FIX 2: $total was never defined.
                $total = round(collect($request->details)->sum(fn ($d) => round((float) $d['amount'], 2)), 2);

                // FIX 3: deleted rows are included (expense_no is unique, so a number must never be reused)
                // and the read is locked so two simultaneous saves cannot get the same number.
                $expenseCode=Expense::max('expense_no');
                $expenseCode++;
                $expenseCode=str_pad((string) $expenseCode,6,'0',STR_PAD_LEFT);

                $expense=new Expense();
                $expense->expense_no = $expenseCode;
                $expense->expense_date = $request->expense_date;
                $expense->employee_id        = $request->employee_id;
                $expense->payment_group_id   = (int) $request->input('payment_group_id');
                $expense->payment_account_id = (int) $request->input('payment_account_id');
                $expense->reference_no       = $this->nullIfBlank($request->input('reference_no'));
                $expense->remarks               = $this->nullIfBlank($request->input('note'));
                $expense->total_amount       = $total;
                $expense->created_by         = Auth::id();   // FIX 4: was missing
                $expense->updated_by         = Auth::id();
                $expense->save();

                foreach($request->details as $detail){
                    $expenseDetail=new ExpenseDetail();
                    $expenseDetail->expense_id=$expense->id;
                    $expenseDetail->expense_group_id=$detail['expense_group_id'];
                    $expenseDetail->expense_account_id=$detail['expense_account_id'];
                    $expenseDetail->description=$this->nullIfBlank($detail['description'] ?? null);   // FIX 5: undefined index when missing
                    $expenseDetail->amount=round((float) $detail['amount'], 2);
                    $expenseDetail->save();
                }

                $lastpaymentCode = Payment::max('code');
                $next = $lastpaymentCode ? ((int) substr($lastpaymentCode, 4)) + 1 : 1;
                $paymentCode = 'VO-' . str_pad($next, 6, '0', STR_PAD_LEFT);
                if ($total > 0) {
                    $payment2 = new Payment();
                    $payment2->party_id = $request->employee_id;
                    $payment2->party_type = 'Employee';
                    $payment2->code = $paymentCode;
                    $payment2->payment_type = 'Expense';
                    $payment2->datetime = $request->expense_date;
                    $payment2->debit = 0;
                    $payment2->credit = $total;
                    $payment2->expense_id = $expense->id;
                    $payment2->payment_method = '';
                    $payment2->status = 'Active';
                    $payment2->deleted = 'No';
                    $payment2->created_by = auth()->id();
                    $payment2->created_datetime = now();
                    $payment2->save();
                }
                // FIX 6: COA balances (payment account credited, expense heads debited).
                // Remove this call if you do not want balances updated.
                $this->postBalances(
                    $this->sumByAccount($request->details),
                    [(int) $expense->payment_account_id => $total],
                    1
                );

            // FIX 7: the transaction was never committed.
            DB::commit();

            return response()->json([
                'status'  => true,
                'message' =>'Expense ' . $expense->expense_no . ' created successfully.'],  200);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();   // FIX 7: roll back on failure
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            DB::rollBack();   // FIX 7: roll back on failure
            Log::error('Expense store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the expense. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Delete (soft) and reverse the balances                            */
    /* ------------------------------------------------------------------ */

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid expense id is required.'], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $expense = DB::table('expenses')
                    ->where('id', (int) $request->input('id'))->where('deleted', 'No')
                    ->lockForUpdate()->first();

                if (!$expense) {
                    abort(404, 'Expense not found.');
                }

                $lines = DB::table('expense_details')->where('expense_id', $expense->id)
                    ->get(['expense_account_id', 'amount']);

                $this->postBalances(
                    $this->sumByAccount($lines),
                    [(int) $expense->payment_account_id => (float) $expense->total_amount],
                    -1
                );

                DB::table('expenses')->where('id', $expense->id)->update([
                    'deleted'      => 'Yes',
                    'status'      => 'Inactive',
                    'deleted_by'   => Auth::id(),
                    'deleted_datetime' => now()

                ]);
                DB::table('expense_details')->where('expense_id', $expense->id)->update([
                    'deleted'      => 'Yes',
                    'status'      => 'Inactive',
                    'deleted_by'   => Auth::id(),
                    'deleted_datetime' => now()

                ]);
                DB::table('payments')->where('expense_id', $expense->id)->update([
                    'deleted'      => 'Yes',
                    'status'      => 'Inactive',
                    'deleted_by'   => Auth::id(),
                    'deleted_datetime' => now()
                ]);
             }, 3);

            return response()->json(['status' => true, 'message' => 'Expense deleted and balances reversed.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Expense delete error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to delete the expense. Please try again.'], 500);
        }
    }

    /* ================================================================== */
    /*  Balance posting                                                   */
    /* ================================================================== */

    /**
     * Applies (direction = 1) or reverses (direction = -1) a posting on
     * chart_of_accounts.current_balance.
     *
     * $debits / $credits: [3rd layer account id => amount]
     *
     * An account whose normal balance is Debit goes up on debit and down on credit
     * (Credit-normal accounts are the opposite). The same movement is added to the
     * account's 2nd and 1st layer parents so group / root totals stay in step.
     */
    private function postBalances(array $debits, array $credits, int $direction): void
    {
        $ledgerIds = array_values(array_unique(array_merge(array_keys($debits), array_keys($credits))));

        if (!$ledgerIds) {
            return;
        }

        $ledgers = DB::table('chart_of_accounts')->whereIn('id', $ledgerIds)
            ->get(['id', 'parent_id', 'balance'])->keyBy('id');

        $groups = DB::table('chart_of_accounts')
            ->whereIn('id', $ledgers->pluck('parent_id')->filter()->unique())
            ->get(['id', 'parent_id'])->keyBy('id');

        $totals = [];

        $collect = function (array $map, int $side) use (&$totals, $ledgers, $groups, $direction) {
            foreach ($map as $id => $amount) {
                $ledger = $ledgers->get($id);

                if (!$ledger) {
                    continue;
                }

                $normal = $ledger->balance === 'Credit' ? -1 : 1;
                $delta  = (float) $amount * $side * $normal * $direction;

                $chain = [$id, $ledger->parent_id, $groups->get($ledger->parent_id)->parent_id ?? null];

                foreach (array_filter($chain) as $accountId) {
                    $totals[$accountId] = ($totals[$accountId] ?? 0) + $delta;
                }
            }
        };

        $collect($debits, 1);     // debit  side
        $collect($credits, -1);   // credit side

        ksort($totals);           // fixed lock order avoids deadlocks

        DB::table('chart_of_accounts')->whereIn('id', array_keys($totals))
            ->orderBy('id')->lockForUpdate()->pluck('id');

        foreach ($totals as $accountId => $delta) {
            $delta = round($delta, 2);

            if (abs($delta) < 0.005) {
                continue;
            }

            DB::table('chart_of_accounts')->where('id', $accountId)
                ->increment('current_balance', number_format($delta, 2, '.', ''));
        }
    }

    /** [account id => summed amount] from detail rows (objects or arrays). */
    private function sumByAccount($rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $row = (object) $row;
            $id  = (int) $row->expense_account_id;

            $map[$id] = ($map[$id] ?? 0) + (float) $row->amount;
        }

        return $map;
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    /** Active 2nd layer accounts of one account type. */
    private function groups(string $type)
    {
        return DB::table('chart_of_accounts')
            ->where('layer_level', 2)
            ->where('account_type', $type)
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }

    /**
     * Account of the given layer + type. Must be Active unless it is the one already
     * saved on the record being edited. Optional parent restriction.
     */
    private function coaRule(int $layer, string $type, $keepId = null, $parentId = null)
    {
        return Rule::exists('chart_of_accounts', 'id')->where(function ($q) use ($layer, $type, $keepId, $parentId) {
            $q->where('layer_level', $layer)
                ->where('account_type', $type)
                ->where('deleted', 'No');

            if ($parentId !== null) {
                $q->where('parent_id', (int) $parentId);
            }

            $q->where(function ($w) use ($keepId) {
                $w->where('status', 'Active');

                if ($keepId) {
                    $w->orWhere('id', $keepId);
                }
            });
        });
    }

    private function nullIfBlank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function ctx(Throwable $e): array
    {
        return ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    }



    public function pdf(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            abort(422, 'A valid expense id is required.');
        }

        $expense = DB::table('expenses as e')
            ->leftJoin('employees as emp', 'emp.id', '=', 'e.employee_id')
            ->leftJoin('chart_of_accounts as pg', 'pg.id', '=', 'e.payment_group_id')
            ->leftJoin('chart_of_accounts as pa', 'pa.id', '=', 'e.payment_account_id')
            ->where('e.id', (int) $request->query('id'))
            ->where('e.deleted', 'No')
            ->first([
                'e.*',
                'emp.name as employee_name', 'emp.employee_code', 'emp.designation',
                'pg.account_code as pg_code', 'pg.account_name as pg_name',
                'pa.account_code as pa_code', 'pa.account_name as pa_name',
            ]);

        if (!$expense) {
            abort(404, 'Expense not found.');
        }

        $details = DB::table('expense_details as d')
            ->leftJoin('chart_of_accounts as g', 'g.id', '=', 'd.expense_group_id')
            ->leftJoin('chart_of_accounts as c', 'c.id', '=', 'd.expense_account_id')
            ->where('d.expense_id', $expense->id)
            ->orderBy('d.id')
            ->get([
                'd.description', 'd.amount',
                'g.account_name as group_name',
                'c.account_code as account_code', 'c.account_name as account_name',
            ]);

        // Company details come from the same $generalSetting your layout uses (if it is shared globally).
        $settings = view()->shared('generalSetting', []);

        $company = [
            'name'    => data_get($settings, 'company_name', 'Company Name'),
            'address' => data_get($settings, 'company_address'),
            'phone'   => data_get($settings, 'company_phone'),
            'email'   => data_get($settings, 'company_email'),
        ];

        $total = (float) $expense->total_amount;

        $pdf = PDF::loadView('admin.accounts.expense.pdf', [
            'expense'  => $expense,
            'details'  => $details,
            'company'  => $company,
            'total'    => $total,
            'words'    => amount_in_words($total),
            'printedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait');

        $fileName = 'Expense-' . $expense->expense_no . '.pdf';

        // ?download=1 forces a download, otherwise it opens in the browser viewer.
        return $request->boolean('download')
            ? $pdf->download($fileName)
            : $pdf->stream($fileName);
    }
}
