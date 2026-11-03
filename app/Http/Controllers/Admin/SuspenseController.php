<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\AccountBalanceTrait;
use App\Traits\SafeJsonErrors;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Suspense (advance / imprest) of investors and employees, and investor capital.
 *
 * One row per transaction in suspense_transactions; each row debits dr_account_id and credits cr_account_id by its
 * amount (AccountingReportController::gl() reads exactly that, the Cash & Bank Book reads the account_id side):
 *  - Paid (employee) / Withdraw (investor): money out as an advance. Dr Suspense Account / Cr cash-bank. Outstanding +.
 *  - Return: unspent money given back. Dr cash-bank / Cr Suspense Account. Outstanding −, never beyond what is outstanding.
 *  - Adjustment: the spent part, no money moves. Dr the chosen Expense ledger (or Owner's Drawings for an investor's
 *    personal use) / Cr Suspense Account. Outstanding −, never beyond what is outstanding.
 *  - Deposit (employee): money in from the employee. Dr cash-bank / Cr Suspense Account (outstanding goes negative =
 *    the office owes the employee).
 *  - Investment (investor): capital in. Dr cash-bank / Cr Owner's Capital. Capital +, outstanding unchanged.
 *  - Capital Withdrawal (investor): capital taken out for good. Dr Owner's Capital (or another equity ledger, e.g.
 *    Owner's Drawings) / Cr cash-bank. Capital −.
 * An investor's capital = opening capital (parties.opening_capital, main branch) + investments − capital withdrawals.
 *
 * Outstanding checks and changes use the WORKING branch (its books hold the advance); lists, cards and statements
 * read the visible branches.
 */
class SuspenseController extends Controller
{
    use AccountBalanceTrait, VoucherHelperTrait, SafeJsonErrors;

    public const PERSON_TYPES = ['Investor', 'Employee'];

    /** txn type => [person type (null = both), outstanding sign, capital sign, money ('out' | 'in' | null)] */
    public const TYPES = [
        'Paid'               => ['Employee', 1, 0, 'out'],
        'Withdraw'           => ['Investor', 1, 0, 'out'],
        'Return'             => [null, -1, 0, 'in'],
        'Adjustment'         => [null, -1, 0, null],
        'Deposit'            => ['Employee', -1, 0, 'in'],
        'Investment'         => ['Investor', 0, 1, 'in'],
        'Capital Withdrawal' => ['Investor', 0, -1, 'out'],
    ];

    /** How the Cash & Bank Book (and the daily cash closing) names each money-moving type. */
    public const CASH_BOOK_TYPES = [
        'Paid' => 'Suspense Paid', 'Withdraw' => 'Suspense Paid', 'Return' => 'Suspense Return', 'Deposit' => 'Employee Deposit',
        'Investment' => 'Investment', 'Capital Withdrawal' => 'Capital Withdrawal',
    ];

    /** Shown on the form under the type. */
    private const HELP = [
        'Paid'               => 'Money given to the employee as an advance for office work (Dr Suspense Account, Cr cash / bank).',
        'Withdraw'           => 'Money taken by the investor as an advance to be returned or accounted for (Dr Suspense Account, Cr cash / bank).',
        'Return'             => 'Unspent money given back (Dr cash / bank, Cr Suspense Account).',
        'Adjustment'         => 'The part spent, accounted for without cash (Dr the chosen expense / drawings ledger, Cr Suspense Account).',
        'Deposit'            => 'Money the employee puts in for the office; the office then owes it to the employee (Dr cash / bank, Cr Suspense Account).',
        'Investment'         => 'Capital invested in the business (Dr cash / bank, Cr Owner\'s Capital).',
        'Capital Withdrawal' => 'Capital taken out of the business for good (Dr Owner\'s Capital / Drawings, Cr cash / bank).',
    ];

    /* ================================================================== */
    /*  Shared rules                                                      */
    /* ================================================================== */

    /** Types a person type may use. */
    public static function typesFor(string $personType): array
    {
        return array_keys(array_filter(self::TYPES, fn ($t) => $t[0] === null || $t[0] === $personType));
    }

    /**
     * The ledgers the module posts to: ['suspense' => Suspense Account (Current Asset), 'capital' => Owner's Capital,
     * 'drawings' => Owner's Drawings]. Found by slug, then by name; $create adds a missing one to the chart.
     */
    public static function ledgers(bool $create = false): array
    {
        $specs = [
            'suspense' => [['suspense-account'], 'Asset', 'Suspense Account', ['current-asset'], 'Suspense%'],
            'capital'  => [['owner-s-capital', 'owner-capital', 'owners-capital'], 'Equity', "Owner's Capital", ['owner-s-equity', 'owners-equity'], '%capital%'],
            'drawings' => [['owner-s-drawings', 'owner-drawings', 'owners-drawings'], 'Equity', "Owner's Drawings", ['owner-s-equity', 'owners-equity'], '%drawing%'],
        ];
        $out = [];
        foreach ($specs as $key => [$slugs, $type, $name, $groups, $like]) {
            $ledger = fn () => DB::table('chart_of_accounts')->where('layer_level', 3)->where('deleted', 'No')->whereIn('slug', $slugs)->orderBy('id')->value('id')
                ?: DB::table('chart_of_accounts')->where('layer_level', 3)->where('deleted', 'No')->where('account_type', $type)
                    ->where('account_name', 'like', $like)->orderBy('id')->value('id');
            $id = $ledger();
            if (!$id && $create) {
                $group = DB::table('chart_of_accounts')->where('layer_level', 2)->where('deleted', 'No')->whereIn('slug', $groups)->orderBy('id')->first()
                    ?: DB::table('chart_of_accounts')->where('layer_level', 2)->where('deleted', 'No')->where('account_type', $type)->orderBy('account_code')->first();
                if ($group) {
                    $prefix = substr((string) $group->account_code, 0, 3);
                    $max = 0;
                    foreach (DB::table('chart_of_accounts')->where('parent_id', $group->id)->lockForUpdate()->pluck('account_code') as $code) {
                        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{2})000$/', (string) $code, $m)) {
                            $max = max($max, (int) $m[1]);
                        }
                    }
                    $id = DB::table('chart_of_accounts')->insertGetId([
                        'account_code' => $prefix . str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT) . '000',
                        'account_name' => $name, 'slug' => $slugs[0], 'layer_level' => 3, 'parent_id' => $group->id,
                        'account_type' => $type, 'balance' => $type === 'Asset' ? 'Debit' : 'Credit', 'current_balance' => 0, 'opening_balance' => 0,
                        'is_system' => 0, 'deleted' => 'No', 'status' => 'Active', 'created_by' => Auth::id(), 'created_date' => now()->toDateString(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $out[$key] = $id ? (int) $id : null;
        }

        return $out;
    }

    /**
     * Totals of one person (or of several: $personId = array) — paid / withdrawn, returned, adjusted, deposited,
     * outstanding, capital invested / withdrawn, opening capital and capital. $own = working branch only (the checks
     * before a change), otherwise the visible branches. $exceptId leaves one row out (the row being edited).
     */
    public static function totals(string $personType, $personId, bool $own = false, ?int $exceptId = null): array
    {
        $ids = array_map('intval', (array) $personId);
        $col = $personType === 'Investor' ? 's.party_id' : 's.employee_id';
        $q = DB::table('suspense_transactions as s');
        $own ? BranchController::scopeOwn($q, 's.branch_id') : BranchController::scope($q, 's.branch_id');
        $rows = $q->where('s.deleted', 'No')->where('s.person_type', $personType)->whereIn($col, $ids ?: [0])
            ->when($exceptId, fn ($w) => $w->where('s.id', '<>', $exceptId))
            ->groupBy($col, 's.txn_type')->selectRaw("$col AS pid, s.txn_type, SUM(s.amount) AS amt")->get();

        $openingVisible = $own ? BranchController::isMainBranch() : BranchController::mainVisible();
        $opening = $personType === 'Investor' && $openingVisible
            ? DB::table('parties')->whereIn('id', $ids ?: [0])->pluck('opening_capital', 'id')->map(fn ($v) => (float) $v)->all() : [];

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['paid' => 0.0, 'returned' => 0.0, 'adjusted' => 0.0, 'deposited' => 0.0, 'invested' => 0.0, 'capital_withdrawn' => 0.0,
                'opening_capital' => round($opening[$id] ?? 0, 2)];
        }
        foreach ($rows as $r) {
            $key = match ($r->txn_type) {
                'Paid', 'Withdraw' => 'paid', 'Return' => 'returned', 'Adjustment' => 'adjusted', 'Deposit' => 'deposited',
                'Investment' => 'invested', 'Capital Withdrawal' => 'capital_withdrawn',
            };
            $out[(int) $r->pid][$key] += (float) $r->amt;
        }
        foreach ($out as $id => $t) {
            $t = array_map(fn ($v) => round($v, 2), $t);
            $t['outstanding'] = round($t['paid'] - $t['returned'] - $t['adjusted'] - $t['deposited'], 2);
            $t['capital'] = round($t['opening_capital'] + $t['invested'] - $t['capital_withdrawn'], 2);
            $out[$id] = $t;
        }

        return is_array($personId) ? $out : ($out[(int) $personId] ?? []);
    }

    /** A person's name row (investor party / employee); $own = an employee of the working branch only. */
    public static function person(string $personType, int $id, bool $own = false): ?object
    {
        if ($personType === 'Investor') {
            return DB::table('parties')->where('id', $id)->where('type', 'Investor')->where('deleted', 'No')
                ->first(['id', 'name', 'code', 'phone', 'email', 'address', 'nid', 'share_percent', 'opening_capital', 'opening_balance_date', 'status']);
        }
        $q = DB::table('employees')->where('id', $id)->where('deleted', 'No');
        $own ? BranchController::scopeOwn($q, 'branch_id') : BranchController::scope($q, 'branch_id');

        return $q->first(['id', 'name', 'employee_code as code', 'phone', 'email', 'branch_id', 'status']);
    }

    /** Which ledgers a row debits and credits. */
    private static function sides(string $type, ?int $cash, ?int $adjust, array $ledgers): array
    {
        return match ($type) {
            'Paid', 'Withdraw'   => [$ledgers['suspense'], $cash],
            'Return', 'Deposit'  => [$cash, $ledgers['suspense']],
            'Adjustment'         => [$adjust, $ledgers['suspense']],
            'Investment'         => [$cash, $ledgers['capital']],
            'Capital Withdrawal' => [$adjust ?: $ledgers['capital'], $cash],
        };
    }

    /** Money each row takes out of its cash / bank ledger (negative = brings in): [account_id => amount]. */
    private static function cashOut(?object $row): array
    {
        if (!$row || !$row->account_id || !(self::TYPES[$row->txn_type][3] ?? null)) {
            return [];
        }

        return [(int) $row->account_id => (self::TYPES[$row->txn_type][3] === 'out' ? 1 : -1) * (float) $row->amount];
    }

    /* ================================================================== */
    /*  Page, list, one row                                               */
    /* ================================================================== */

    public function index(Request $request)
    {
        $ledgers = self::ledgers();
        $ledgerRows = fn (array $types) => DB::table('chart_of_accounts as a')->leftJoin('chart_of_accounts as g', 'g.id', '=', 'a.parent_id')
            ->where('a.layer_level', 3)->whereIn('a.account_type', $types)->where('a.deleted', 'No')->where('a.status', 'Active')
            ->orderBy('a.account_code')->get(['a.id', 'a.account_code', 'a.account_name', 'a.account_type', 'g.account_name as group_name']);

        return view('admin.accounts.suspense.index', [
            'investors'      => DB::table('parties')->where('type', 'Investor')->where('deleted', 'No')->orderBy('name')->get(['id', 'name', 'code', 'phone', 'status']),
            // the filter lists the visible branches' employees; the form gives to the working branch's employees
            'employees'      => BranchController::scope(DB::table('employees'), 'branch_id')->where('deleted', 'No')->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'branch_id', 'status']),
            'ownEmployees'   => BranchController::scopeOwn(DB::table('employees'), 'branch_id')->where('deleted', 'No')->where('status', 'Active')
                ->orderBy('name')->pluck('id')->all(),
            'cashAccounts'   => PayrollSetupController::payrollLedgers('Liquid Asset'),
            'adjustAccounts' => $ledgerRows(['Expense', 'Equity']),
            'ledgers'        => $ledgers,
            'types'          => self::TYPES,
            'help'           => self::HELP,
            'preset'         => (string) $request->query('person', ''),
        ]);
    }

    /** Filters: person_type, person ("Investor-5" / "Employee-9"), txn_type, from, to. */
    public function list(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'person_type' => ['nullable', Rule::in(self::PERSON_TYPES)],
            'person'      => ['nullable', 'regex:/^(Investor|Employee)-\d+$/'],
            'txn_type'    => ['nullable', Rule::in(array_keys(self::TYPES))],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        if ($validator->fails()) {
            return response()->json(['data' => [], 'message' => $validator->errors()->first()], 422);
        }

        try {
            [$pType, $pId] = $request->filled('person') ? explode('-', (string) $request->query('person')) : [null, null];
            $q = BranchController::scope(self::baseQuery(), 's.branch_id')
                ->when($request->filled('person_type'), fn ($w) => $w->where('s.person_type', $request->query('person_type')))
                ->when($pType, fn ($w) => $w->where('s.person_type', $pType)->where($pType === 'Investor' ? 's.party_id' : 's.employee_id', (int) $pId))
                ->when($request->filled('txn_type'), fn ($w) => $w->where('s.txn_type', $request->query('txn_type')))
                ->when($request->filled('from'), fn ($w) => $w->where('s.txn_date', '>=', $request->query('from')))
                ->when($request->filled('to'), fn ($w) => $w->where('s.txn_date', '<=', $request->query('to')));
            $rows = $q->orderByDesc('s.txn_date')->orderByDesc('s.id')->get();

            $badge = ['Paid' => 'bg-warning text-dark', 'Withdraw' => 'bg-warning text-dark', 'Return' => 'bg-success', 'Adjustment' => 'bg-info text-dark',
                'Deposit' => 'bg-success', 'Investment' => 'bg-primary', 'Capital Withdrawal' => 'bg-danger'];
            $canEdit = PermissionController::allows('suspense.edit');
            $canDelete = PermissionController::allows('suspense.delete');
            $data = $rows->values()->map(function ($r, $i) use ($badge, $canEdit, $canDelete) {
                $id = (int) $r->id;
                $pid = $r->person_type === 'Investor' ? (int) $r->party_id : (int) $r->employee_id;
                $t = self::TYPES[$r->txn_type];
                $effect = $t[1] ? 'Outstanding ' . ($t[1] > 0 ? '+' : '−') : 'Capital ' . ($t[2] > 0 ? '+' : '−');
                $date = Carbon::parse($r->txn_date);

                return [
                    $i + 1,
                    '<span class="fw-semibold">' . e($r->voucher_no) . '</span>' . ($r->reference ? '<div class="text-muted small">Ref: ' . e($r->reference) . '</div>' : '')
                        . branch_tag($r->branch_id),
                    '<span class="d-none">' . $date->format('Y-m-d') . '</span>' . e($date->format('d M Y')),
                    e($r->person_name ?? '-') . '<div class="text-muted small">' . e($r->person_type) . ($r->person_code ? ' · ' . e($r->person_code) : '') . '</div>',
                    '<span class="badge ' . ($badge[$r->txn_type] ?? 'bg-secondary') . '">' . e($r->txn_type) . '</span><div class="text-muted small">' . e($effect) . '</div>',
                    $r->account_name ? '<div>' . e($r->account_name) . '</div>' . ($r->adjust_name ? '<div class="text-muted small">' . e($r->adjust_name) . '</div>' : '')
                        : ($r->adjust_name ? e($r->adjust_name) : '<span class="text-muted">-</span>'),
                    e($r->purpose ?? ''),
                    '<span class="d-none">' . sprintf('%021.2f', (float) $r->amount) . '</span><span class="d-block text-end fw-semibold">' . number_format((float) $r->amount, 2) . '</span>',
                    action_dropdown(array_merge(
                        [['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => "Suspense.view($id)"],
                            ['label' => 'Statement', 'icon' => 'fas fa-file-invoice', 'onclick' => "Suspense.statement('" . $r->person_type . "', $pid)"]],
                        $canEdit ? [['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => "Suspense.edit($id)"]] : [],
                        $canDelete ? [['divider' => true], ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => "Suspense.remove($id)"]] : []
                    ), $r->branch_id),
                ];
            });

            // Cards: the selected person's totals (visible branches), or the filtered rows' totals.
            if ($pType) {
                $sum = self::totals($pType, (int) $pId);
                $sum['person_type'] = $pType;
            } else {
                $by = fn (array $types) => round((float) $rows->whereIn('txn_type', $types)->sum('amount'), 2);
                $sum = ['paid' => $by(['Paid', 'Withdraw']), 'returned' => $by(['Return']), 'adjusted' => $by(['Adjustment']), 'deposited' => $by(['Deposit']),
                    'invested' => $by(['Investment']), 'capital_withdrawn' => $by(['Capital Withdrawal']), 'opening_capital' => 0.0, 'person_type' => null];
                $sum['outstanding'] = round($sum['paid'] - $sum['returned'] - $sum['adjusted'] - $sum['deposited'], 2);
                $sum['capital'] = round($sum['invested'] - $sum['capital_withdrawn'], 2);
            }

            return response()->json(['data' => $data, 'summary' => $sum, 'count' => $rows->count()]);
        } catch (Throwable $e) {
            Log::error('Suspense list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load the suspense entries.'], 500);
        }
    }

    public function show(Request $request)
    {
        $r = BranchController::scope(self::baseQuery(), 's.branch_id')->where('s.id', (int) $request->query('id'))->first();
        if (!$r) {
            return response()->json(['status' => false, 'message' => 'Suspense entry not found.'], 404);
        }

        return response()->json(['status' => true, 'data' => [
            'id' => $r->id, 'voucher_no' => $r->voucher_no, 'txn_date' => Carbon::parse($r->txn_date)->format('Y-m-d'),
            'txn_date_label' => Carbon::parse($r->txn_date)->format('d M Y'), 'person_type' => $r->person_type,
            'person_id' => $r->person_type === 'Investor' ? $r->party_id : $r->employee_id, 'person_name' => $r->person_name, 'person_code' => $r->person_code,
            'txn_type' => $r->txn_type, 'amount' => number_format((float) $r->amount, 2, '.', ''), 'account_id' => $r->account_id, 'account_name' => $r->account_name,
            'adjust_account_id' => $r->adjust_account_id, 'adjust_name' => $r->adjust_name, 'dr_name' => $r->dr_name, 'cr_name' => $r->cr_name,
            'purpose' => $r->purpose, 'reference' => $r->reference, 'branch' => BranchController::name((int) $r->branch_id),
            'own' => BranchController::owns($r->branch_id), 'created_by' => $r->created_by_name,
        ]]);
    }

    /** A person's totals: the working branch's (what the checks use) and the visible branches'. */
    public function balance(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'person_type' => ['required', Rule::in(self::PERSON_TYPES)],
            'person_id'   => ['required', 'integer'],
            'except_id'   => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }
        $type = $request->query('person_type');
        $id = (int) $request->query('person_id');
        if (!self::person($type, $id)) {
            return response()->json(['status' => false, 'message' => $type . ' not found.'], 404);
        }

        return response()->json(['status' => true, 'own' => self::totals($type, $id, true, $request->filled('except_id') ? (int) $request->query('except_id') : null),
            'all' => self::totals($type, $id), 'branch' => BranchController::name((int) BranchController::activeId())]);
    }

    /* ================================================================== */
    /*  Save (create / update when "id" is posted) and delete             */
    /* ================================================================== */

    public function store(Request $request)
    {
        if ($request->filled('id')) {
            BranchController::guardOwn('suspense_transactions', $request->input('id'));   // another branch's entry: 403
        }

        $personType = (string) $request->input('person_type');
        $type = (string) $request->input('txn_type');
        $isInvestor = $personType === 'Investor';
        $moves = (self::TYPES[$type][3] ?? null) !== null;

        $validator = Validator::make($request->all(), [
            'id'                => ['nullable', 'integer'],
            'person_type'       => ['required', Rule::in(self::PERSON_TYPES)],
            'person_id'         => ['required', 'integer', $isInvestor
                ? Rule::exists('parties', 'id')->where('type', 'Investor')->where('deleted', 'No')
                : Rule::exists('employees', 'id')->where('deleted', 'No')->where('branch_id', (int) BranchController::activeId())],
            'txn_type'          => ['required', Rule::in(in_array($personType, self::PERSON_TYPES, true) ? self::typesFor($personType) : [])],
            'txn_date'          => ['required', 'date'],
            'amount'            => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'account_id'        => $moves ? ['required', 'integer', $this->coaExists(3, 'Liquid Asset'), BranchController::ownLedgerRule()] : ['nullable'],
            'adjust_account_id' => match ($type) {
                'Adjustment'         => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('layer_level', 3)->where('deleted', 'No')
                    ->whereIn('account_type', $isInvestor ? ['Expense', 'Equity'] : ['Expense'])],
                'Capital Withdrawal' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('layer_level', 3)->where('deleted', 'No')
                    ->where('account_type', 'Equity')],
                default              => ['nullable'],
            },
            'purpose'           => ['nullable', 'string', 'max:255'],
            'reference'         => ['nullable', 'string', 'max:100'],
        ], [
            'person_id.required'         => $isInvestor ? 'Select the investor.' : 'Select the employee.',
            'person_id.exists'           => $isInvestor ? 'Select an investor.' : 'Select an employee of this branch.',
            'txn_type.in'                => 'Select a transaction type for ' . ($isInvestor ? 'an investor' : 'an employee') . '.',
            'account_id.required'        => 'Select the cash / bank account.',
            'account_id.exists'          => 'Select a cash / bank account of this branch.',
            'adjust_account_id.required' => 'Select the ledger the spent amount goes to.',
            'adjust_account_id.exists'   => $type === 'Capital Withdrawal' ? 'Select an equity ledger (e.g. Owner\'s Capital).'
                : ($isInvestor ? 'Select an expense ledger or Owner\'s Drawings.' : 'Select an expense ledger.'),
            'amount.min'                 => 'Amount must be greater than zero.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $row = DB::transaction(function () use ($request, $personType, $type, $isInvestor, $moves) {
                $ledgers = self::ledgers(true);
                abort_unless($ledgers['suspense'] && $ledgers['capital'], 422, 'The Chart of Accounts needs a Current Asset and an Owner\'s Equity group first.');

                $personId = (int) $request->input('person_id');
                $old = null;
                if ($request->filled('id')) {
                    $old = BranchController::scopeOwn(DB::table('suspense_transactions'), 'branch_id')->where('id', (int) $request->input('id'))
                        ->where('deleted', 'No')->lockForUpdate()->first();
                    abort_unless($old, 404, 'Suspense entry not found.');
                    if ($closed = AccountingReportController::closedDateMessage($old->txn_date)) {
                        abort(422, $closed);
                    }
                    abort_if($old->person_type !== $personType || (int) ($isInvestor ? $old->party_id : $old->employee_id) !== $personId, 422,
                        'The person of an entry cannot be changed. Delete it and add a new one.');
                }

                // one change per person at a time: the outstanding check below reads what the others saved
                DB::table($isInvestor ? 'parties' : 'employees')->where('id', $personId)->lockForUpdate()->first();
                $person = self::person($personType, $personId, true);
                $amount = round((float) $request->input('amount'), 2);

                $before = self::totals($personType, $personId, true, $old ? (int) $old->id : null);
                if (in_array($type, ['Return', 'Adjustment'], true)) {
                    $branch = BranchController::multiBranch() ? ' in ' . BranchController::name((int) BranchController::activeId()) : '';
                    abort_if($before['outstanding'] <= 0.004, 422, $person->name . ' has nothing outstanding' . $branch . ' to ' . ($type === 'Return' ? 'return' : 'adjust') . '.');
                    abort_if($amount > $before['outstanding'] + 0.004, 422, ($type === 'Return' ? 'Return' : 'Adjustment') . ' of ' . number_format($amount, 2)
                        . ' is more than ' . $person->name . '\'s outstanding ' . number_format($before['outstanding'], 2) . $branch . '.');
                }
                self::assertSettledWithinPaid($before, $type, $amount, $person->name);

                $cash = $moves ? (int) $request->input('account_id') : null;
                $adjust = in_array($type, ['Adjustment', 'Capital Withdrawal'], true)
                    ? ((int) $request->input('adjust_account_id') ?: ($type === 'Capital Withdrawal' ? $ledgers['capital'] : null)) : null;
                [$dr, $cr] = self::sides($type, $cash, $adjust, $ledgers);

                // cash drawer: only what this change takes out beyond the old entry
                $new = (object) ['account_id' => $cash, 'txn_type' => $type, 'amount' => $amount];
                $needed = [];
                foreach (array_unique(array_merge(array_keys(self::cashOut($old)), array_keys(self::cashOut($new)))) as $acc) {
                    $delta = round((self::cashOut($new)[$acc] ?? 0) - (self::cashOut($old)[$acc] ?? 0), 2);
                    if ($delta > 0.004) {
                        $needed[$acc] = $delta;
                    }
                }
                ReportController::ensureCashLines($needed);

                $values = [
                    'txn_date' => $request->input('txn_date'), 'txn_type' => $type, 'amount' => $amount,
                    'account_id' => $cash, 'adjust_account_id' => $adjust, 'dr_account_id' => $dr, 'cr_account_id' => $cr,
                    'purpose' => $this->nullIfBlank($request->input('purpose')), 'reference' => $this->nullIfBlank($request->input('reference')),
                    'updated_at' => now(),
                ];
                if ($old) {
                    $this->postBalances([(int) $old->dr_account_id => (float) $old->amount], [(int) $old->cr_account_id => (float) $old->amount], -1);
                    DB::table('suspense_transactions')->where('id', $old->id)->update($values + ['updated_by' => Auth::id(), 'updated_date' => now()]);
                    $id = (int) $old->id;
                } else {
                    $last = DB::table('suspense_transactions')->where('voucher_no', 'like', 'SUS-%')->lockForUpdate()->max('voucher_no');
                    $next = $last && preg_match('/^SUS-(\d+)/', $last, $m) ? (int) $m[1] + 1 : 1;
                    $id = DB::table('suspense_transactions')->insertGetId($values + [
                        'voucher_no' => 'SUS-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                        'person_type' => $personType, 'party_id' => $isInvestor ? $personId : null, 'employee_id' => $isInvestor ? null : $personId,
                        'deleted' => 'No', 'status' => 'Active', 'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(),
                    ]);
                }
                $this->postBalances([$dr => $amount], [$cr => $amount], 1);

                return DB::table('suspense_transactions')->where('id', $id)->first();
            }, self::SAVE_ATTEMPTS);

            return response()->json([
                'status'  => true,
                'message' => $row->voucher_no . ' ' . ($request->filled('id') ? 'updated' : 'saved') . ': ' . $type . ' ' . number_format((float) $row->amount, 2) . '.',
                'totals'  => self::totals($personType, (int) $request->input('person_id')),
            ]);
        } catch (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($status === 500) {
                Log::error('Suspense store error', $this->ctx($e));
            }

            return response()->json(['status' => false, 'message' => self::safeError($e, 'Unable to save the suspense entry. Please try again.')], $status);
        }
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => ['required', 'integer', 'min:1']]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid entry id is required.'], 422);
        }
        BranchController::guardOwn('suspense_transactions', $request->input('id'));

        try {
            $row = DB::transaction(function () use ($request) {
                $row = BranchController::scopeOwn(DB::table('suspense_transactions'), 'branch_id')->where('id', (int) $request->input('id'))
                    ->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($row, 404, 'Suspense entry not found.');
                if ($closed = AccountingReportController::closedDateMessage($row->txn_date)) {
                    abort(422, $closed);
                }
                $isInvestor = $row->person_type === 'Investor';
                $personId = (int) ($isInvestor ? $row->party_id : $row->employee_id);
                DB::table($isInvestor ? 'parties' : 'employees')->where('id', $personId)->lockForUpdate()->first();

                // returns / adjustments must stay within what was paid out once this entry is gone
                $after = self::totals($row->person_type, $personId, true, (int) $row->id);
                $name = self::person($row->person_type, $personId)->name ?? $row->person_type;
                self::assertSettledWithinPaid($after, null, 0, $name);

                // money this entry brought into a cash drawer leaves it again
                $needed = array_filter(array_map(fn ($v) => round(-$v, 2), self::cashOut($row)), fn ($v) => $v > 0.004);
                ReportController::ensureCashLines($needed);

                $this->postBalances([(int) $row->dr_account_id => (float) $row->amount], [(int) $row->cr_account_id => (float) $row->amount], -1);
                DB::table('suspense_transactions')->where('id', $row->id)->update($this->deletedColumns() + ['updated_at' => now()]);

                return $row;
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => $row->voucher_no . ' deleted and its posting reversed.']);
        } catch (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($status === 500) {
                Log::error('Suspense delete error', $this->ctx($e));
            }

            return response()->json(['status' => false, 'message' => self::safeError($e, 'Unable to delete the suspense entry. Please try again.')], $status);
        }
    }

    /**
     * After a change, returns + adjustments may not be more than what was paid / withdrawn (an advance that was
     * accounted for cannot lose its payout). $type / $amount = the entry being saved (null when deleting).
     */
    private static function assertSettledWithinPaid(array $t, ?string $type, float $amount, string $name): void
    {
        $paid = $t['paid'] + (in_array($type, ['Paid', 'Withdraw'], true) ? $amount : 0);
        $settled = $t['returned'] + $t['adjusted'] + (in_array($type, ['Return', 'Adjustment'], true) ? $amount : 0);
        if ($settled > $paid + 0.004) {
            abort(422, 'Returns and adjustments of ' . $name . ' (' . number_format($settled, 2) . ') would be more than the money paid out ('
                . number_format($paid, 2) . '). Change or delete the returns / adjustments first.');
        }
    }

    /* ================================================================== */
    /*  Statement (page data, PDF, Excel)                                 */
    /* ================================================================== */

    /**
     * A person's suspense statement in the visible branches: opening (before $from), each entry with the running
     * outstanding balance (and, for an investor, the running capital), totals and closing.
     */
    public static function statement(string $personType, int $personId, ?string $from, ?string $to): array
    {
        $person = self::person($personType, $personId);
        abort_unless($person, 404, $personType . ' not found.');
        $to = $to ?: now()->toDateString();

        $rows = BranchController::scope(self::baseQuery(), 's.branch_id')->where('s.person_type', $personType)
            ->where($personType === 'Investor' ? 's.party_id' : 's.employee_id', $personId)
            ->where('s.txn_date', '<=', $to)->orderBy('s.txn_date')->orderBy('s.id')->get();

        $openingCapital = $personType === 'Investor' && BranchController::mainVisible() ? (float) $person->opening_capital : 0.0;
        $balance = 0.0;
        $capital = $openingCapital;
        $lines = [];
        $tot = ['advance' => 0.0, 'settled' => 0.0, 'capital_in' => 0.0, 'capital_out' => 0.0];
        $opening = null;
        foreach ($rows as $r) {
            [, $bSign, $cSign] = self::TYPES[$r->txn_type];
            $amt = (float) $r->amount;
            if ($from && $r->txn_date < $from) {
                $balance += $bSign * $amt;
                $capital += $cSign * $amt;
                continue;
            }
            $opening ??= ['balance' => round($balance, 2), 'capital' => round($capital, 2)];
            $balance = round($balance + $bSign * $amt, 2);
            $capital = round($capital + $cSign * $amt, 2);
            $tot['advance'] += $bSign > 0 ? $amt : 0;
            $tot['settled'] += $bSign < 0 ? $amt : 0;
            $tot['capital_in'] += $cSign > 0 ? $amt : 0;
            $tot['capital_out'] += $cSign < 0 ? $amt : 0;
            $lines[] = [
                'date' => Carbon::parse($r->txn_date), 'voucher' => $r->voucher_no, 'type' => $r->txn_type,
                'particulars' => trim(implode(' · ', array_filter([$r->account_name, $r->adjust_name, $r->purpose, $r->reference ? 'Ref: ' . $r->reference : null]))),
                'branch' => BranchController::multiBranch() ? BranchController::name((int) $r->branch_id) : '',
                'advance' => $bSign > 0 ? $amt : 0.0, 'settled' => $bSign < 0 ? $amt : 0.0, 'balance' => $balance,
                'capital_change' => $cSign * $amt, 'capital' => $capital,
            ];
        }
        $opening ??= ['balance' => round($balance, 2), 'capital' => round($capital, 2)];

        $text = [
            $personType => $person->name . ($person->code ? ' (' . $person->code . ')' : ''),
            'Period'    => ($from ? Carbon::parse($from)->format('d M Y') : 'Beginning') . '  to  ' . Carbon::parse($to)->format('d M Y'),
            'Branch'    => BranchController::visibleLabel(),
        ];

        return [
            'person_type'  => $personType,
            'person'       => $person,
            'investor'     => $personType === 'Investor',
            'from'         => $from,
            'to'           => $to,
            'opening'      => $opening,
            'lines'        => $lines,
            'totals'       => array_map(fn ($v) => round($v, 2), $tot),
            'closing'      => ['balance' => round($balance, 2), 'capital' => round($capital, 2)],
            'filters_text' => $text,
        ];
    }

    private function statementInput(Request $request): array
    {
        $v = Validator::make($request->query(), [
            'person_type' => ['required', Rule::in(self::PERSON_TYPES)],
            'person_id'   => ['required', 'integer'],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        if ($v->fails()) {
            abort(422, $v->errors()->first());
        }

        return self::statement($request->query('person_type'), (int) $request->query('person_id'), $request->query('from') ?: null, $request->query('to') ?: null);
    }

    public function statementData(Request $request)
    {
        try {
            $s = $this->statementInput($request);
            $s['lines'] = array_map(fn ($l) => ['date' => $l['date']->format('d M Y')] + $l, $s['lines']);

            return response()->json(['status' => true, 'data' => $s,
                'totals_now' => self::totals($s['person_type'], (int) $s['person']->id)]);
        } catch (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json(['status' => false, 'message' => self::safeError($e, 'Unable to load the statement.')], $status);
        }
    }

    public function statementPdf(Request $request)
    {
        $s = $this->statementInput($request);
        $pdf = SettingController::pdf('admin.accounts.suspense.statement-pdf', $s + [
            'printedAt' => now()->format('d M Y, h:i A'), 'printedBy' => Auth::user()->name ?? '',
        ], $s['investor'] ? 'landscape' : 'portrait');
        $file = 'Suspense-Statement-' . preg_replace('/[^A-Za-z0-9]+/', '-', $s['person']->name) . '.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    public function statementExcel(Request $request)
    {
        $s = $this->statementInput($request);
        $company = SettingController::pdfCompany();
        $inv = $s['investor'];
        $heads = array_merge(['Date', 'Voucher', 'Type', 'Particulars'], $s['lines'] && $s['lines'][0]['branch'] !== '' ? ['Branch'] : [],
            ['Paid / Withdrawn', 'Returned / Adjusted', 'Outstanding'], $inv ? ['Capital +/−', 'Capital'] : []);
        $withBranch = in_array('Branch', $heads, true);
        $last = chr(ord('A') + count($heads) - 1);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Suspense Statement');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $sheet->setCellValue('A1', $company['name'] ?? '')->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', 'Suspense Statement')->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));
        $row = 3;
        foreach ($s['filters_text'] + ['Printed' => now()->format('d M Y, h:i A') . ' by ' . (Auth::user()->name ?? '')] as $k => $v) {
            $sheet->setCellValue("A{$row}", $k . ':')->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$row}", $v)->mergeCells("B{$row}:{$last}{$row}");
            $row++;
        }
        $row++;
        $first = $row;
        foreach ($heads as $i => $h) {
            $sheet->setCellValue(chr(ord('A') + $i) . $row, $h);
        }
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]]]);
        $row++;

        $put = function (array $cells) use ($sheet, &$row) {
            foreach (array_values($cells) as $i => $v) {
                $sheet->setCellValue(chr(ord('A') + $i) . $row, $v);
            }
            $row++;
        };
        $money = function (array $l) use ($inv) {
            return array_merge([$l['advance'] ?: null, $l['settled'] ?: null, $l['balance']], $inv ? [$l['capital_change'] ?: null, $l['capital']] : []);
        };
        $pad = $withBranch ? [null] : [];
        $put(array_merge([null, null, 'Opening balance', null], $pad, [null, null, $s['opening']['balance']], $inv ? [null, $s['opening']['capital']] : []));
        $sheet->getStyle('A' . ($row - 1) . ":{$last}" . ($row - 1))->getFont()->setItalic(true);
        foreach ($s['lines'] as $l) {
            $put(array_merge([$l['date']->format('d M Y'), $l['voucher'], $l['type'], $l['particulars']], $withBranch ? [$l['branch']] : [], $money($l)));
        }
        $put(array_merge([null, null, 'Total / Closing', null], $pad, [$s['totals']['advance'], $s['totals']['settled'], $s['closing']['balance']],
            $inv ? [round($s['totals']['capital_in'] - $s['totals']['capital_out'], 2), $s['closing']['capital']] : []));
        $sheet->getStyle('A' . ($row - 1) . ":{$last}" . ($row - 1))->applyFromArray(['font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('soft')]]]);

        $numFrom = chr(ord('A') + ($withBranch ? 5 : 4));
        $sheet->getStyle("A{$first}:{$last}" . ($row - 1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => self::xl('border_strong')]]]]);
        $sheet->getStyle("{$numFrom}{$first}:{$last}" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;"0.00"');
        $sheet->getStyle("{$numFrom}{$first}:{$last}{$first}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (range('A', $last) as $c) {
            $sheet->getColumnDimension($c)->setWidth(match (true) { $c === 'D' => 46, $c <= 'C' => 14, default => 17 });
        }
        $sheet->getPageSetup()->setOrientation($inv ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);

        $file = 'Suspense-Statement-' . preg_replace('/[^A-Za-z0-9]+/', '-', $s['person']->name) . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    /** Entries with person, account and user names (live rows only). */
    private static function baseQuery()
    {
        return DB::table('suspense_transactions as s')
            ->leftJoin('parties as pa', 'pa.id', '=', 's.party_id')
            ->leftJoin('employees as em', 'em.id', '=', 's.employee_id')
            ->leftJoin('chart_of_accounts as ca', 'ca.id', '=', 's.account_id')
            ->leftJoin('chart_of_accounts as aa', 'aa.id', '=', 's.adjust_account_id')
            ->leftJoin('chart_of_accounts as dr', 'dr.id', '=', 's.dr_account_id')
            ->leftJoin('chart_of_accounts as cr', 'cr.id', '=', 's.cr_account_id')
            ->leftJoin('users as u', 'u.id', '=', 's.created_by')
            ->where('s.deleted', 'No')
            ->select('s.*', DB::raw("CASE WHEN s.person_type = 'Investor' THEN pa.name ELSE em.name END AS person_name"),
                DB::raw("CASE WHEN s.person_type = 'Investor' THEN pa.code ELSE em.employee_code END AS person_code"),
                'ca.account_name as account_name', 'aa.account_name as adjust_name', 'dr.account_name as dr_name', 'cr.account_name as cr_name',
                'u.name as created_by_name');
    }

    /** A Settings > Theme Colors palette color as Excel RRGGBB. */
    private static function xl(string $key): string
    {
        return strtoupper(ltrim(SettingController::pdfColors()[$key], '#'));
    }
}
