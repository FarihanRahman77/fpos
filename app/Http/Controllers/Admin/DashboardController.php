<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dashboard: every figure is read for the branches the user sees (BranchController::scope), so a user permitted in
 * several branches sees them combined. Each widget has a permission gate (GATES); a widget the user may not open is
 * neither queried nor shown. Money series are read once, grouped by day, and every window (period, previous period,
 * today, months, this month vs last) is summed from those rows in PHP - no per-row queries, nothing cached.
 */
class DashboardController extends Controller
{
    public const PERIODS = [
        'today' => 'Today',
        'week'  => 'This week',
        'month' => 'This month',
        'year'  => 'This year',
    ];

    /** widget => any of these permissions shows it. */
    public const GATES = [
        'sales'      => ['sales.view', 'pos.view'],
        'profit'     => ['statements.view', 'reports.view'],
        'purchases'  => ['purchases.view'],
        'expenses'   => ['expenses.view', 'bills.view', 'reports.view'],
        'collected'  => ['payment_receive.view', 'reports.view'],
        'cash'       => ['reports.view', 'cash_closing.view'],
        'closing'    => ['cash_closing.view'],
        'receivable' => ['parties.view', 'payment_receive.view', 'reports.view'],
        'payable'    => ['parties.view', 'payments.view', 'reports.view'],
        'stock'      => ['products.view', 'stock.view'],
        'pipeline'   => ['quotations.view', 'sale_orders.view'],
        'payments'   => ['payments.view', 'payment_receive.view'],
        'attendance' => ['attendance.view'],
        'payroll'    => ['payroll.view'],
        'loans'      => ['loans.view'],
        'leave'      => ['leave.view'],
        'holidays'   => ['holidays.view', 'leave.view'],
        'employees'  => ['employees.view', 'attendance.view', 'payroll.view'],
        'reports'    => ['reports.view'],
    ];

    /** Aging buckets (days) as in the Receivable / Payable Aging reports. */
    public const AGING = ['b0' => '0–30 days', 'b31' => '31–60 days', 'b61' => '61–90 days', 'b90' => 'Over 90 days'];

    private array $can = [];

    public function index(Request $request)
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';
        [$from, $to, $prevFrom, $prevTo] = $this->range($period);
        $can = $this->can = self::permissions();

        try {
            $facts = $this->facts($prevFrom);
            $kpi = $this->window($facts, $from, $to);
            $prev = $this->window($facts, $prevFrom, $prevTo);
            foreach (['sales', 'orders', 'avg', 'profit', 'purchases', 'expenses', 'collected', 'net'] as $key) {
                $kpi['change'][$key] = $prev[$key] ? ($kpi[$key] - $prev[$key]) / abs($prev[$key]) * 100 : null;
            }

            $today = Carbon::today();
            $todayTotals = $this->window($facts, $today, $today);
            $yesterday = $this->window($facts, $today->copy()->subDay(), $today->copy()->subDay());
            $todayTotals['change']['sales'] = $yesterday['sales'] ? ($todayTotals['sales'] - $yesterday['sales']) / $yesterday['sales'] * 100 : null;

            $hr = $this->hr();
            $data = [
                'period'      => $period,
                'periodLabel' => self::PERIODS[$period],
                'from'        => $from,
                'to'          => $to,
                'can'         => $can,
                'multi'       => BranchController::multiBranch(),
                'kpi'         => $kpi,
                'today'       => $todayTotals,
                'monthly'     => $this->monthly($facts),
                'trend'       => $this->monthVsLast($facts),
                'salesMix'    => $can['sales'] ? $this->salesMix($from, $to) : [],
                'topProducts' => $can['sales'] ? $this->topProducts($from, $to) : [],
                'cash'        => $can['cash'] ? $this->cashPositions() : ['methods' => [], 'accounts' => [], 'total' => 0.0],
                'closing'     => $can['closing'] ? $this->closingToday() : null,
                'dues'        => ($can['receivable'] || $can['payable']) ? $this->dues() : null,
                'stock'       => $can['stock'] ? $this->stockHealth(10) : null,
                'expiring'    => $can['stock'] ? $this->expiring() : null,
                'pipeline'    => $can['pipeline'] ? $this->pipeline() : null,
                'activity'    => $this->activity(),
                'attendance'  => $can['attendance'] ? $this->attendanceToday($hr['employees']) : null,
                'hr'          => $hr,
            ];
        } catch (Throwable $e) {
            Log::error('Dashboard error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            $data = ['failed' => true, 'period' => $period, 'periodLabel' => self::PERIODS[$period], 'can' => $can];
        }

        $data['currency'] = DB::table('settings')->value('currency_symbol') ?: '৳';

        return view('admin.includes.dashboard', $data);
    }

    /** widget => bool for the signed-in user (see GATES). */
    public static function permissions(): array
    {
        return array_map(fn ($any) => PermissionController::allows($any), self::GATES);
    }

    /** Top-bar bell: things that need attention now (cached a minute per user and branch). */
    public static function notifications(): array
    {
        try {
            $key = 'nav-alerts:' . auth()->id() . ':' . BranchController::loggedInId() . ':' . implode('-', BranchController::visibleIds());

            return \Illuminate\Support\Facades\Cache::remember($key, 60, function () {
                $alerts = [];
                $self = new self();
                $can = self::permissions();

                if ($can['stock']) {
                    $stock = $self->stockHealth(0);
                    if ($stock['outCount'] + $stock['lowCount'] > 0) {
                        $alerts[] = ['icon' => 'bi-box-seam', 'title' => ($stock['outCount'] + $stock['lowCount']) . ' product(s) low or out of stock',
                            'text' => $stock['outCount'] . ' out, ' . $stock['lowCount'] . ' low', 'url' => route('dashboard') . '#stock'];
                    }
                }
                if ($can['leave']) {
                    $pending = BranchController::scope(DB::table('leave_applications as la')->join('employees as e', 'e.id', '=', 'la.employee_id'), 'e.branch_id')->where('la.deleted', 'No')->where('la.leave_status', 'Pending')->count();
                    if ($pending) {
                        $alerts[] = ['icon' => 'bi-calendar2-x', 'title' => $pending . ' leave request(s) waiting', 'text' => 'Approve or reject', 'url' => route('admin.leave.index')];
                    }
                }
                // One alert per processed month with unpaid salary, oldest first; each opens that month's Pay Salary.
                if ($can['payroll']) {
                    foreach (PayrollRunController::unpaidMonths() as $u) {
                        $alerts[] = ['icon' => 'bi-people', 'title' => 'Salary due ' . number_format($u['due'], 2) . ' · ' . $u['label'],
                            'text' => $u['employees'] . ' employee(s) processed but not fully paid', 'url' => $u['url']];
                    }
                }
                if (PermissionController::allows('sale_orders.view')) {
                    $orders = $self->b('sale_orders')->where('deleted', 'No')->whereIn('order_status', ['Pending', 'Confirmed', 'Partial'])
                        ->whereNotNull('delivery_date')->where('delivery_date', '<=', now()->toDateString())->count();
                    if ($orders) {
                        $alerts[] = ['icon' => 'bi-truck', 'title' => $orders . ' sale order(s) due for delivery', 'text' => 'Delivery date today or earlier', 'url' => route('sales.orders.index')];
                    }
                }
                if (PermissionController::allows('quotations.view')) {
                    $quotes = $self->b('sale_quotations')->where('deleted', 'No')->whereIn('quotation_status', ['Draft', 'Sent'])
                        ->whereBetween('valid_until', [now()->toDateString(), now()->addDays(3)->toDateString()])->count();
                    if ($quotes) {
                        $alerts[] = ['icon' => 'bi-file-earmark-text', 'title' => $quotes . ' quotation(s) expire soon', 'text' => 'Within 3 days', 'url' => route('admin.quotations.index')];
                    }
                }

                return $alerts;
            });
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Current period and the same-length period right before it. */
    private function range(string $period): array
    {
        $now = Carbon::now();

        [$from, $to] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'year'  => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };

        // Compare like with like: month-to-date against the same days of last month, etc.
        [$prevFrom, $prevTo] = match ($period) {
            'today' => [$from->copy()->subDay(), $to->copy()->subDay()],
            'week'  => [$from->copy()->subWeek(), $to->copy()->subWeek()],
            'year'  => [$from->copy()->subYear(), $to->copy()->subYear()],
            default => [$from->copy()->subMonthNoOverflow(), $to->copy()->subMonthNoOverflow()],
        };

        return [$from, $to, $prevFrom, $prevTo];
    }

    /** DB::table() limited to the branches the user sees ("table" or "table as alias"). */
    private function b(string $table)
    {
        $alias = preg_match('/ as (\w+)$/', $table, $m) ? $m[1] : $table;

        return BranchController::scope(DB::table($table), $alias . '.branch_id');
    }

    private function sales()
    {
        return $this->b('sales')->where('deleted', 'No')->where('status', 'Active');
    }

    /* ------------------------------------------------------------------ */
    /*  Money series: one grouped-by-day query per source                  */
    /* ------------------------------------------------------------------ */

    /**
     * Daily totals from the earliest date any widget needs (previous period, 12 months, last month) to today:
     * source => [Y-m-d => [field => value]]. Sources the user may not see are left empty.
     */
    private function facts(Carbon $prevFrom): array
    {
        $start = min($prevFrom->copy()->startOfDay(), Carbon::now()->startOfMonth()->subMonths(11), Carbon::now()->startOfMonth()->subMonthNoOverflow());
        $s = $start->toDateString();
        $e = Carbon::today()->toDateString();
        $c = $this->can;
        $byDay = fn ($query, string $dayExpr, string $select) => $query->selectRaw("$dayExpr AS d, $select")->groupByRaw($dayExpr)->get()
            ->mapWithKeys(fn ($r) => [$r->d => array_map('floatval', array_diff_key((array) $r, ['d' => 1]))])->all();

        $f = array_fill_keys(['sales', 'cost', 'returns', 'purchases', 'expenses', 'bills', 'payroll', 'collected'], []);

        if ($c['sales'] || $c['profit']) {
            $f['sales'] = $byDay($this->sales()->whereBetween('sale_date', [$s, $e]), 'sale_date',
                'COUNT(*) AS n, COALESCE(SUM(grand_total), 0) AS total, COALESCE(SUM(vat_amount), 0) AS vat');
            $f['cost'] = $byDay(BranchController::scope(DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id'), 's.branch_id')
                ->where('s.deleted', 'No')->where('s.status', 'Active')->where('si.deleted', 'No')->whereBetween('s.sale_date', [$s, $e]),
                's.sale_date', 'COALESCE(SUM(si.qty * si.purchase_price), 0) AS cost, COALESCE(SUM(si.qty), 0) AS qty');
            $f['returns'] = $byDay($this->b('sale_returns')->where('deleted', 'No')->where('status', 'Active')
                ->where('datetime', '>=', $s)->where('datetime', '<', Carbon::tomorrow()->toDateString()), 'DATE(datetime)', 'SUM(grand_total) AS total');
        }
        if ($c['purchases']) {
            $f['purchases'] = $byDay($this->b('purchases')->where('deleted', 'No')->where('status', 'Active')->whereBetween('purchase_date', [$s, $e]),
                'purchase_date', 'SUM(grand_total) AS total');
        }
        if ($c['expenses'] || $c['profit']) {
            $f['expenses'] = $byDay($this->b('expenses')->where('deleted', 'No')->whereBetween('expense_date', [$s, $e]), 'expense_date', 'SUM(total_amount) AS total');
            $f['bills'] = $byDay($this->b('bills')->where('deleted', 'No')->whereBetween('bill_date', [$s, $e]), 'bill_date', 'SUM(grand_total) AS total');
            $f['payroll'] = $byDay($this->b('payroll_runs')->where('run_status', 'Posted')->whereBetween('run_date', [$s, $e]),
                'run_date', 'SUM(total_net + total_pf + total_loan) AS total');
        }
        if ($c['collected']) {
            $f['collected'] = $byDay($this->b('payments')->where('deleted', 'No')->where('status', 'Active')
                ->whereIn('party_type', ['Customer', 'Walk-In Customer'])->where('payment_type', 'like', '%Receive%')
                ->where('datetime', '>=', $s)->where('datetime', '<', Carbon::tomorrow()->toDateString()), 'DATE(datetime)', 'SUM(debit) AS total');
        }

        return $f;
    }

    /** Sum of one field of one source over [from, to] (dates, inclusive). */
    private static function sumDays(array $series, Carbon $from, Carbon $to, string $field = 'total'): float
    {
        $a = $from->toDateString();
        $b = $to->toDateString();
        $sum = 0.0;
        foreach ($series as $d => $row) {
            if ($d >= $a && $d <= $b) {
                $sum += $row[$field] ?? 0;
            }
        }

        return $sum;
    }

    /** The KPI figures of one window. */
    private function window(array $f, Carbon $from, Carbon $to): array
    {
        $sum = fn ($src, $field = 'total') => self::sumDays($f[$src], $from, $to, $field);
        $sold = $sum('sales');
        $n = (int) $sum('sales', 'n');
        $netSold = $sold - $sum('sales', 'vat'); // VAT collected is owed to the government, not income
        $cost = $sum('cost', 'cost');
        $expenses = $sum('expenses') + $sum('bills') + $sum('payroll');

        return [
            'sales'     => $sold,
            'orders'    => $n,
            'avg'       => $n ? $sold / $n : 0.0,
            'items'     => $sum('cost', 'qty'),
            'returns'   => $sum('returns'),
            'cost'      => $cost,
            'profit'    => $netSold - $cost,
            'margin'    => $netSold > 0 ? ($netSold - $cost) / $netSold * 100 : null,
            'net'       => $netSold - $cost - $expenses,
            'purchases' => $sum('purchases'),
            'expenses'  => $expenses,
            'collected' => $sum('collected'),
        ];
    }

    /** Last 12 months, oldest first. */
    private function monthly(array $f): array
    {
        $byMonth = [];
        foreach (['sales' => 'total', 'purchases' => 'total', 'expenses' => 'total', 'bills' => 'total', 'payroll' => 'total', 'collected' => 'total'] as $src => $field) {
            foreach ($f[$src] as $d => $row) {
                $ym = substr($d, 0, 7);
                $byMonth[$src][$ym] = ($byMonth[$src][$ym] ?? 0) + ($row[$field] ?? 0);
            }
        }
        $get = fn ($src, $ym) => (float) ($byMonth[$src][$ym] ?? 0);

        $months = [];
        for ($m = Carbon::now()->startOfMonth()->subMonths(11); $m->lte(Carbon::now()); $m->addMonth()) {
            $ym = $m->format('Y-m');
            $months[] = [
                'label'     => $m->format('M'),
                'title'     => $m->format('F Y'),
                'sales'     => $get('sales', $ym),
                'purchases' => $get('purchases', $ym),
                'expenses'  => $get('expenses', $ym) + $get('bills', $ym) + $get('payroll', $ym),
                'collected' => $get('collected', $ym),
            ];
        }

        return $months;
    }

    /** Sales per day of month, this month (to today) against last month, with running totals. */
    private function monthVsLast(array $f): array
    {
        $now = Carbon::today();
        $thisStart = $now->copy()->startOfMonth();
        $lastStart = $thisStart->copy()->subMonthNoOverflow();
        $days = max($thisStart->daysInMonth, $lastStart->daysInMonth);
        $rows = [];
        $runThis = $runLast = 0.0;
        for ($i = 1; $i <= $days; $i++) {
            $dThis = $i <= $thisStart->daysInMonth ? $thisStart->copy()->day($i) : null;
            $dLast = $i <= $lastStart->daysInMonth ? $lastStart->copy()->day($i) : null;
            $vThis = $dThis && $dThis->lte($now) ? (float) ($f['sales'][$dThis->toDateString()]['total'] ?? 0) : null;
            $vLast = $dLast ? (float) ($f['sales'][$dLast->toDateString()]['total'] ?? 0) : null;
            if ($vThis !== null) {
                $runThis += $vThis;
            }
            if ($vLast !== null) {
                $runLast += $vLast;
            }
            $rows[] = ['day' => $i, 'this' => $vThis, 'last' => $vLast,
                'runThis' => $vThis === null ? null : $runThis, 'runLast' => $vLast === null ? null : $runLast];
        }
        $sameDayLast = 0.0;
        foreach ($rows as $r) {
            if ($r['day'] <= $now->day && $r['runLast'] !== null) {
                $sameDayLast = $r['runLast'];
            }
        }

        return [
            'days'      => $rows,
            'thisLabel' => $thisStart->format('F'),
            'lastLabel' => $lastStart->format('F'),
            'thisTotal' => $runThis,
            'lastTotal' => $runLast,
            'lastToDate' => $sameDayLast,
            'today'     => $now->day,
        ];
    }

    private function salesMix(Carbon $from, Carbon $to): array
    {
        $rows = $this->sales()->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('sale_type, COUNT(*) AS n, SUM(grand_total) AS total')->groupBy('sale_type')
            ->orderByDesc('total')->get();

        $sum = (float) $rows->sum('total');

        return $rows->map(fn ($r) => [
            'type'  => $r->sale_type ?: 'Other',
            'count' => (int) $r->n,
            'total' => (float) $r->total,
            'share' => $sum > 0 ? $r->total / $sum * 100 : 0,
        ])->all();
    }

    /* ------------------------------------------------------------------ */
    /*  Money                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Cash & bank of the branches the user sees: each ledger's opening balance + those branches' money movements
     * (the Cash & Bank Book engine), not the company-wide ledger balance.
     */
    private function cashPositions(): array
    {
        $report = new ReportController();
        $ctx = (object) ['filters' => []];
        $balance = [];
        foreach ($report->liquidAssetAccounts($ctx) as $key => $a) {
            $balance[$key] = ['method' => $a['method'], 'name' => $a['account'], 'balance' => (float) $a['opening_balance']];
        }
        foreach ($report->liquidAssetMovements($ctx, null, Carbon::now()->endOfDay()) as $m) {
            if (!isset($balance[$m->key])) {
                $method = substr($m->key, 2) ?: 'Unspecified';
                $balance[$m->key] = ['method' => $method, 'name' => $method . ' — no account', 'balance' => 0.0];
            }
            $balance[$m->key]['balance'] += $m->in - $m->out;
        }

        $accounts = collect($balance)->map(fn ($a) => ['method' => $a['method'], 'name' => $a['name'], 'balance' => round($a['balance'], 2)])
            ->sortBy([['method', 'asc'], ['balance', 'desc']])->values();
        $methods = $accounts->groupBy('method')->map(fn ($g, $name) => ['name' => $name, 'balance' => round($g->sum('balance'), 2)])->values()->all();

        return [
            'methods'  => $methods,
            'accounts' => $accounts->all(),
            'total'    => round($accounts->sum('balance'), 2),
        ];
    }

    /** Today's daily cash closing for each visible branch: saved or not. */
    private function closingToday(): array
    {
        $rows = $this->b('cash_day_closings')->where('closing_date', Carbon::today()->toDateString())
            ->get(['branch_id', 'closing_amount', 'saved_at']);

        return [
            'saved'    => $rows->count(),
            'branches' => count(BranchController::visibleIds()),
            'amount'   => (float) $rows->sum('closing_amount'),
            'at'       => $rows->max('saved_at'),
        ];
    }

    /**
     * Receivables and payables from the party ledger (same rule as PartyDueTrait), aged like the Aging reports:
     * payments settle the oldest charges first, so what is left is aged from the newest charges. One query.
     */
    private function dues(): array
    {
        $isMain = BranchController::mainVisible();   // once, not per party row; the opening balance is the main branch's
        $visible = BranchController::visibleIds();
        $today = Carbon::today();
        $d30 = $today->copy()->subDays(30)->toDateString();
        $d60 = $today->copy()->subDays(60)->toDateString();
        $d90 = $today->copy()->subDays(90)->toDateString();
        $bucketSql = '';
        foreach (['credit' => 'c', 'debit' => 'd'] as $col => $p) {
            $bucketSql .= ", COALESCE(SUM(CASE WHEN pm.datetime >= '$d30' THEN pm.$col END), 0) AS {$p}0"
                . ", COALESCE(SUM(CASE WHEN pm.datetime >= '$d60' AND pm.datetime < '$d30' THEN pm.$col END), 0) AS {$p}31"
                . ", COALESCE(SUM(CASE WHEN pm.datetime >= '$d90' AND pm.datetime < '$d60' THEN pm.$col END), 0) AS {$p}61";
        }

        $parties = DB::table('parties as p')->where('p.deleted', 'No')->where('p.type', '<>', 'Investor')
            ->leftJoin('payments as pm', function ($j) use ($visible) {
                $j->on('pm.party_id', '=', 'p.id')->where('pm.deleted', 'No')->where('pm.status', 'Active')
                    ->whereIn('pm.branch_id', $visible)
                    // party_id also holds employee ids on employee expense rows
                    ->where(fn ($q) => $q->whereNull('pm.party_type')->orWhere('pm.party_type', '<>', 'Employee'));
            })
            ->groupBy('p.id', 'p.name', 'p.phone', 'p.type', 'p.opening_balance', 'p.opening_balance_type', 'p.opening_balance_date', 'p.created_at')
            ->selectRaw('p.id, p.name, p.phone, p.type, p.opening_balance, p.opening_balance_type, p.opening_balance_date, p.created_at,
                COALESCE(SUM(pm.debit), 0) AS debit, COALESCE(SUM(pm.credit), 0) AS credit' . $bucketSql)
            ->get()
            ->map(function ($p) use ($isMain, $today) {
                $supplier = $p->type === 'Supplier';
                $opening = $isMain ? (float) $p->opening_balance : 0.0;   // opening belongs to the main branch
                if ($p->opening_balance_type === 'Advance') {
                    $opening = -$opening;
                }
                $p->due = $supplier ? $opening + $p->debit - $p->credit : $opening + $p->credit - $p->debit;

                // Charges per bucket (customer: credit, supplier: debit); the rest of the charges are older than 90 days.
                $k = $supplier ? 'd' : 'c';
                $charges = ['b0' => (float) $p->{$k . '0'}, 'b31' => (float) $p->{$k . '31'}, 'b61' => (float) $p->{$k . '61'}];
                if ($opening > 0) {
                    $age = Carbon::parse($p->opening_balance_date ?: $p->created_at ?: $today)->startOfDay()->diffInDays($today, false);
                    $bucket = $age <= 30 ? 'b0' : ($age <= 60 ? 'b31' : ($age <= 90 ? 'b61' : null));
                    if ($bucket) {
                        $charges[$bucket] += $opening;
                    }
                }
                $left = max(0.0, $p->due);
                $p->aging = [];
                foreach (['b0', 'b31', 'b61'] as $b) {
                    $p->aging[$b] = min($left, $charges[$b]);
                    $left -= $p->aging[$b];
                }
                $p->aging['b90'] = $left;

                return $p;
            });

        $side = function ($rows) {
            $aging = array_fill_keys(array_keys(self::AGING), 0.0);
            foreach ($rows as $r) {
                foreach ($r->aging as $b => $v) {
                    $aging[$b] += $v;
                }
            }

            return [
                'total' => (float) $rows->sum('due'),
                'count' => $rows->count(),
                'aging' => $aging,
                'top'   => $rows->sortByDesc('due')->take(8)->values()->all(),
            ];
        };

        return [
            'receivable' => $side($parties->where('type', '!=', 'Supplier')->where('due', '>', 0.004)),
            'payable'    => $side($parties->where('type', 'Supplier')->where('due', '>', 0.004)),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Sales & stock                                                      */
    /* ------------------------------------------------------------------ */

    private function topProducts(Carbon $from, Carbon $to): array
    {
        return BranchController::scope(DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id'), 's.branch_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.deleted', 'No')->where('s.status', 'Active')->where('si.deleted', 'No')
            ->whereBetween('s.sale_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.image')
            ->selectRaw('p.id, p.name, p.sku, p.image, SUM(si.qty) AS qty, SUM(si.total) AS revenue,
                SUM(si.total - si.qty * si.purchase_price) AS profit')
            ->orderByDesc('revenue')->limit(8)->get()
            ->map(function ($r) {
                $r->image_url = $r->image ? asset('uploads/products/' . $r->image) : null;

                return $r;
            })->all();
    }

    /** Stock on hand of the visible branches per product; $take = how many low / out products to list. */
    private function stockHealth(int $take = 10): array
    {
        $products = DB::table('products as p')->where('p.deleted', 'No')->where('p.status', 'Active')
            ->leftJoin('stocks as st', function ($j) {
                $j->on('st.product_id', '=', 'p.id')->where('st.deleted', 'No')->where('st.status', 'Active');
                $j->whereIn('st.branch_id', BranchController::visibleIds());
            })
            ->groupBy('p.id', 'p.name', 'p.sku', 'p.stock_alert', 'p.purchase_price')
            ->selectRaw('p.id, p.name, p.sku, p.stock_alert, p.purchase_price,
                COALESCE(SUM(st.in_qty), 0) - COALESCE(SUM(st.out_qty), 0) AS stock')
            ->get();

        $low = $products->filter(fn ($p) => $p->stock > 0 && $p->stock <= (float) $p->stock_alert);
        $out = $products->filter(fn ($p) => $p->stock <= 0);

        return [
            'products' => $products->count(),
            'units'    => (float) $products->sum(fn ($p) => max(0, $p->stock)),
            'value'    => (float) $products->sum(fn ($p) => max(0, $p->stock) * (float) $p->purchase_price),
            'lowCount' => $low->count(),
            'outCount' => $out->count(),
            // Low stock first (still sellable, reorder now), then out of stock.
            'alerts'   => $take ? $low->sortBy('stock')->concat($out->sortBy('name'))->take($take)->values()->all() : [],
        ];
    }

    /** Batches still in stock that are expired or expire within 30 days, soonest first. */
    private function expiring(): array
    {
        $today = Carbon::today();
        $held = BranchController::scope(DB::table('stocks as st'), 'st.branch_id')->where('st.deleted', 'No')->where('st.status', 'Active')
            ->whereNotNull('st.batch_id')->groupBy('st.batch_id', 'st.branch_id')
            ->selectRaw('st.batch_id, st.branch_id, SUM(st.in_qty - st.out_qty) AS qty')->havingRaw('SUM(st.in_qty - st.out_qty) > 0.0005');

        $rows = DB::table('product_batches as b')->joinSub($held, 'k', 'k.batch_id', '=', 'b.id')
            ->join('products as p', 'p.id', '=', 'b.product_id')->where('p.deleted', 'No')
            ->whereNotNull('b.expiry_date')->where('b.expiry_date', '<=', $today->copy()->addDays(30)->toDateString())
            ->orderBy('b.expiry_date')->orderBy('p.name')->limit(200)
            ->get(['p.name', 'p.sku', 'p.purchase_price', 'b.batch_no', 'b.expiry_date', 'b.unit_cost', 'k.qty', 'k.branch_id'])
            ->map(function ($r) use ($today) {
                $r->days = $today->diffInDays(Carbon::parse($r->expiry_date), false);
                $r->value = (float) $r->qty * (float) ($r->unit_cost ?: $r->purchase_price);

                return $r;
            });

        return [
            'expired'  => $rows->where('days', '<', 0)->count(),
            'soon'     => $rows->where('days', '>=', 0)->count(),
            'value'    => (float) $rows->sum('value'),
            'rows'     => $rows->take(10)->values()->all(),
        ];
    }

    private function pipeline(): array
    {
        $today = Carbon::today()->toDateString();
        $quotes = $this->b('sale_quotations')->where('deleted', 'No')
            ->selectRaw("COALESCE(SUM(quotation_status IN ('Draft', 'Sent')), 0) AS open_n,
                COALESCE(SUM(CASE WHEN quotation_status IN ('Draft', 'Sent') THEN grand_total END), 0) AS open_total,
                COALESCE(SUM(quotation_status IN ('Draft', 'Sent') AND valid_until BETWEEN ? AND ?), 0) AS expiring,
                COALESCE(SUM(quotation_status = 'Accepted'), 0) AS accepted, COUNT(*) AS n", [$today, Carbon::today()->addDays(3)->toDateString()])
            ->first();

        $open = fn () => $this->b('sale_orders as o')->where('o.deleted', 'No')->whereIn('o.order_status', ['Pending', 'Confirmed', 'Partial']);
        $orders = $open()->selectRaw('COUNT(*) AS n, COALESCE(SUM(o.grand_total), 0) AS total, COALESCE(SUM(o.due_amount), 0) AS due,
            COALESCE(SUM(o.delivery_date IS NOT NULL AND o.delivery_date <= ?), 0) AS late', [$today])->first();
        $list = $open()->leftJoin('parties as p', 'p.id', '=', 'o.customer_id')
            ->orderByRaw('o.delivery_date IS NULL, o.delivery_date')->orderBy('o.id')->limit(8)
            ->get(['o.id', 'o.order_no', 'o.order_status', 'o.delivery_date', 'o.grand_total', 'o.branch_id', 'p.name as customer', 'p.phone']);

        return [
            'quoteOpen'      => (int) $quotes->open_n,
            'quoteOpenValue' => (float) $quotes->open_total,
            'quoteExpiring'  => (int) $quotes->expiring,
            'quoteAccepted'  => (int) $quotes->accepted,
            'quoteTotal'     => (int) $quotes->n,
            'orderOpen'      => (int) $orders->n,
            'orderValue'     => (float) $orders->total,
            'orderDue'       => (float) $orders->due,
            'orderLate'      => (int) $orders->late,
            'orders'         => $list->all(),
        ];
    }

    /** Latest money events across modules the user may see, newest first - one UNION query. */
    private function activity(): array
    {
        $take = 10;
        $c = $this->can;
        $parts = [];
        $cols = fn (string $kind, string $at, string $ref, string $amount, string $who, string $branch, string $x1, string $x2 = 'NULL') =>
            "'$kind' AS kind, $at AS at, $ref AS ref, $amount AS amount, $who AS who, $branch AS branch_id, $x1 AS x1, $x2 AS x2";

        if ($c['sales']) {
            $parts[] = $this->b('sales as s')->leftJoin('parties as p', 'p.id', '=', 's.customer_id')
                ->where('s.deleted', 'No')->where('s.status', 'Active')->orderByDesc('s.id')->limit($take)
                ->selectRaw($cols('sale', 'COALESCE(s.created_date, s.sale_date)', 's.invoice_no', 's.grand_total', 'p.name', 's.branch_id', 's.sale_type'));
        }
        if ($c['purchases']) {
            $parts[] = $this->b('purchases as s')->leftJoin('parties as p', 'p.id', '=', 's.supplier_id')
                ->where('s.deleted', 'No')->where('s.status', 'Active')->orderByDesc('s.id')->limit($take)
                ->selectRaw($cols('purchase', 'COALESCE(s.created_date, s.purchase_date)', 's.invoice_no', 's.grand_total', 'p.name', 's.branch_id', 'NULL'));
        }
        if ($c['payments']) {
            $types = array_values(array_filter([
                PermissionController::allows('payment_receive.view') ? 'Payment Receive' : null,
                PermissionController::allows('payments.view') ? 'Payment' : null,
                PermissionController::allows(['payments.view', 'bills.view']) ? 'Bill Payment' : null,
            ]));
            $parts[] = $this->b('payments as m')->leftJoin('parties as p', 'p.id', '=', 'm.party_id')
                ->where('m.deleted', 'No')->where('m.status', 'Active')->whereIn('m.payment_type', $types ?: ['-'])->orderByDesc('m.id')->limit($take)
                ->selectRaw($cols('payment', 'm.datetime', 'm.code', 'm.debit + m.credit', 'p.name', 'm.branch_id', 'm.payment_type'));
        }
        if (PermissionController::allows('expenses.view')) {
            $parts[] = $this->b('expenses as x')->where('x.deleted', 'No')->orderByDesc('x.id')->limit($take)
                ->selectRaw($cols('expense', 'COALESCE(x.created_date, x.expense_date)', 'x.expense_no', 'x.total_amount', 'x.remarks', 'x.branch_id', 'NULL'));
        }
        if ($c['payroll']) {
            $parts[] = $this->b('salary_payments as sp')->where('sp.payment_status', 'Posted')->orderByDesc('sp.id')->limit($take)
                ->selectRaw($cols('salary', 'COALESCE(sp.created_date, sp.payment_date)', 'sp.payment_no', 'sp.total_paid', 'NULL', 'sp.branch_id', 'sp.month', 'sp.employee_count'));
        }
        if ($c['loans']) {
            $parts[] = $this->b('employee_loans as l')->join('employees as e', 'e.id', '=', 'l.employee_id')
                ->where('l.deleted', 'No')->where('l.loan_status', '<>', 'Cancelled')->orderByDesc('l.id')->limit($take)
                ->selectRaw($cols('loan', 'COALESCE(l.created_date, l.issue_date)', 'l.loan_no', 'l.amount', 'e.name', 'l.branch_id', 'l.loan_type'));
        }
        if (PermissionController::allows('suspense.view')) {
            $parts[] = $this->b('suspense_transactions as su')->leftJoin('parties as sp', 'sp.id', '=', 'su.party_id')
                ->leftJoin('employees as se', 'se.id', '=', 'su.employee_id')->where('su.deleted', 'No')->orderByDesc('su.id')->limit($take)
                ->selectRaw($cols('suspense', 'COALESCE(su.created_date, su.txn_date)', 'su.voucher_no', 'su.amount',
                    "CASE WHEN su.person_type = 'Investor' THEN sp.name ELSE se.name END", 'su.branch_id', 'su.txn_type', 'su.person_type'));
        }
        if (!$parts) {
            return [];
        }

        $union = array_shift($parts);
        foreach ($parts as $p) {
            $union->unionAll($p);
        }

        $meta = [
            'sale'     => ['bi-bag-check', 'success', true],
            'purchase' => ['bi-bag-plus', 'warning', false],
            'expense'  => ['bi-receipt', 'danger', false],
            'salary'   => ['bi-people', 'accent', false],
            'loan'     => ['bi-piggy-bank', 'accent', false],
            'suspense' => ['bi-wallet2', 'accent', false],
        ];

        return DB::query()->fromSub($union, 'u')->whereNotNull('at')->orderByDesc('at')->limit($take)->get()
            ->map(function ($r) use ($meta) {
                if ($r->kind === 'payment') {
                    $in = $r->x1 === 'Payment Receive';
                    [$icon, $tone, $title] = [$in ? 'bi-cash-coin' : 'bi-send', $in ? 'success' : 'warning', $in ? 'Payment received' : $r->x1];
                } else {
                    [$icon, $tone, $in] = $meta[$r->kind];
                    if ($r->kind === 'suspense') {
                        $in = in_array($r->x1, ['Return', 'Deposit', 'Investment'], true);
                        $tone = $in ? 'success' : ($r->x1 === 'Adjustment' ? 'accent' : 'warning');
                    }
                    $title = match ($r->kind) {
                        'sale'     => trim(($r->x1 ?: '') . ' sale'),
                        'purchase' => 'Purchase',
                        'expense'  => 'Expense',
                        'salary'   => 'Salary paid',
                        'loan'     => ($r->x1 ?: 'Loan') . ' given',
                        'suspense' => match ($r->x1) {
                            'Investment' => 'Investment received', 'Capital Withdrawal' => 'Capital withdrawn', 'Deposit' => 'Employee deposit',
                            default => 'Suspense ' . strtolower((string) $r->x1),
                        },
                    };
                }
                $who = match ($r->kind) {
                    'sale'   => $r->who ?: 'Walk-In Customer',
                    'salary' => ($r->x1 ? Carbon::parse($r->x1)->format('F Y') : '') . ' · ' . (int) $r->x2 . ' employee(s)',
                    default  => $r->who ?: '-',
                };

                return ['kind' => $r->kind, 'icon' => $icon, 'tone' => $tone, 'title' => ucfirst($title), 'ref' => $r->ref, 'who' => $who,
                    'amount' => (float) $r->amount, 'in' => $in, 'branch_id' => $r->branch_id, 'when' => Carbon::parse($r->at)];
            })->all();
    }

    /* ------------------------------------------------------------------ */
    /*  People                                                             */
    /* ------------------------------------------------------------------ */

    /** Today's attendance of the visible branches: count per status, plus approved leave and not yet marked. */
    private function attendanceToday(int $employees): array
    {
        $rows = $this->b('attendances')->where('deleted', 'No')->where('date', Carbon::today()->toDateString())
            ->selectRaw('attendance_status AS s, COUNT(*) AS n')->groupBy('attendance_status')->pluck('n', 's');
        $get = fn ($s) => (int) ($rows[$s] ?? 0);
        $marked = (int) $rows->sum();

        return [
            'present'  => $get('Present') + $get('Half Day'),
            'late'     => $get('Late'),
            'absent'   => $get('Absent'),
            'leave'    => $get('Leave'),
            'off'      => $get('Holiday') + $get('Weekly Off'),
            'marked'   => $marked,
            'unmarked' => max(0, $employees - $marked),
            'total'    => max($employees, $marked),
        ];
    }

    private function hr(): array
    {
        $c = $this->can;
        $today = Carbon::today()->toDateString();
        $out = ['employees' => 0, 'payrollMonth' => null, 'unpaidMonths' => [], 'unpaidTotal' => 0.0, 'loans' => null,
            'onLeave' => 0, 'pending' => 0, 'holidays' => []];

        if ($c['employees']) {
            $out['employees'] = $this->b('employees')->where('deleted', 'No')->where('status', 'Active')
                ->where(fn ($q) => $q->whereNull('resign_date')->orWhere('resign_date', '>', $today))->count();
        }
        if ($c['payroll']) {
            // This month's payroll: processed lines, net and paid.
            $run = BranchController::scope(DB::table('payroll_run_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id'), 'r.branch_id')
                ->where('r.month', Carbon::now()->startOfMonth()->toDateString())->where('r.run_status', 'Posted')
                ->selectRaw('COUNT(*) AS n, COALESCE(SUM(i.net_payable), 0) AS net, COALESCE(SUM(i.paid_amount), 0) AS paid')->first();
            $out['payrollMonth'] = ['processed' => (int) $run->n, 'net' => (float) $run->net, 'paid' => (float) $run->paid,
                'due' => round((float) $run->net - (float) $run->paid, 2)];
            // Every processed month that still has salary to pay (oldest first) and their total.
            $out['unpaidMonths'] = PayrollRunController::unpaidMonths();
            $out['unpaidTotal'] = round(array_sum(array_column($out['unpaidMonths'], 'due')), 2);
        }
        if ($c['loans']) {
            $loans = $this->b('employee_loans')->where('deleted', 'No')->where('loan_status', 'Running')
                ->selectRaw('COUNT(*) AS n, COALESCE(SUM(amount - recovered), 0) AS balance')->first();
            $out['loans'] = ['count' => (int) $loans->n, 'balance' => (float) $loans->balance];
        }
        if ($c['leave']) {
            $leave = BranchController::scope(DB::table('leave_applications as la')->join('employees as e', 'e.id', '=', 'la.employee_id'), 'e.branch_id')
                ->where('la.deleted', 'No')
                ->selectRaw("COALESCE(SUM(la.leave_status = 'Pending'), 0) AS pending,
                    COALESCE(SUM(la.leave_status = 'Approved' AND la.from_date <= ? AND la.to_date >= ?), 0) AS on_leave", [$today, $today])->first();
            $out['pending'] = (int) $leave->pending;
            $out['onLeave'] = (int) $leave->on_leave;
        }
        if ($c['holidays']) {
            $out['holidays'] = DB::table('holidays')->where('deleted', 'No')->where('to_date', '>=', $today)
                ->orderBy('from_date')->limit(6)->get(['title', 'from_date', 'to_date', 'type'])->all();
        }

        return $out;
    }
}
