<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
    public const PERIODS = [
        'today' => 'Today',
        'week'  => 'This week',
        'month' => 'This month',
        'year'  => 'This year',
    ];

    public function index(Request $request)
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';
        [$from, $to, $prevFrom, $prevTo] = $this->range($period);

        try {
            $data = [
                'period'      => $period,
                'periodLabel' => self::PERIODS[$period],
                'from'        => $from,
                'to'          => $to,
                'kpi'         => $this->kpis($from, $to, $prevFrom, $prevTo),
                'today'       => $this->todaySnapshot(),
                'monthly'     => $this->monthlyTrend(),
                'daily'       => $this->dailySales(),
                'salesMix'    => $this->salesMix($from, $to),
                'cash'        => $this->cashPositions(),
                'dues'        => $this->dues(),
                'topProducts' => $this->topProducts($from, $to),
                'stock'       => $this->stockHealth(),
                'recentSales' => $this->recentSales(),
                'activity'    => $this->activity(),
                'pipeline'    => $this->pipeline(),
                'hr'          => $this->hr(),
            ];
        } catch (Throwable $e) {
            Log::error('Dashboard error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            $data = ['failed' => true, 'period' => $period, 'periodLabel' => self::PERIODS[$period]];
        }

        $data['currency'] = DB::table('settings')->value('currency_symbol') ?: '৳';

        return view('admin.includes.dashboard', $data);
    }

    /** Top-bar bell: things that need attention now (cached a minute per user and branch). */
    public static function notifications(): array
    {
        try {
            $key = 'nav-alerts:' . auth()->id() . ':' . (BranchController::current() ?? 'all');

            return \Illuminate\Support\Facades\Cache::remember($key, 60, function () {
                $alerts = [];
                $self = new self();

                $stock = $self->stockHealth();
                if ($stock['outCount'] + $stock['lowCount'] > 0) {
                    $alerts[] = ['icon' => 'bi-box-seam', 'title' => ($stock['outCount'] + $stock['lowCount']) . ' product(s) low or out of stock',
                        'text' => $stock['outCount'] . ' out, ' . $stock['lowCount'] . ' low', 'url' => route('dashboard') . '#stock'];
                }
                $pending = BranchController::scope(DB::table('leave_applications as la')->join('employees as e', 'e.id', '=', 'la.employee_id'), 'e.branch_id')->where('la.deleted', 'No')->where('la.leave_status', 'Pending')->count();
                if ($pending) {
                    $alerts[] = ['icon' => 'bi-calendar2-x', 'title' => $pending . ' leave request(s) waiting', 'text' => 'Approve or reject', 'url' => route('admin.leave.index')];
                }
                $due = (float) BranchController::scope(DB::table('payroll_run_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id'), 'r.branch_id')
                    ->where('r.run_status', 'Posted')->sum(DB::raw('i.net_payable - i.paid_amount'));
                if ($due > 0.004) {
                    $alerts[] = ['icon' => 'bi-people', 'title' => 'Salary due ' . number_format($due, 2), 'text' => 'Processed but not fully paid', 'url' => route('admin.reports.salary-sheet')];
                }
                $orders = $self->b('sale_orders')->where('deleted', 'No')->whereIn('order_status', ['Pending', 'Confirmed', 'Partial'])
                    ->whereNotNull('delivery_date')->where('delivery_date', '<=', now()->toDateString())->count();
                if ($orders) {
                    $alerts[] = ['icon' => 'bi-truck', 'title' => $orders . ' sale order(s) due for delivery', 'text' => 'Delivery date today or earlier', 'url' => route('sales.orders.index')];
                }
                $quotes = $self->b('sale_quotations')->where('deleted', 'No')->whereIn('quotation_status', ['Draft', 'Sent'])
                    ->whereBetween('valid_until', [now()->toDateString(), now()->addDays(3)->toDateString()])->count();
                if ($quotes) {
                    $alerts[] = ['icon' => 'bi-file-earmark-text', 'title' => $quotes . ' quotation(s) expire soon', 'text' => 'Within 3 days', 'url' => route('admin.quotations.index')];
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

    /** DB::table() limited to the branch being viewed ("table" or "table as alias"). */
    private function b(string $table)
    {
        $alias = preg_match('/ as (\w+)$/', $table, $m) ? $m[1] : $table;

        return BranchController::scope(DB::table($table), $alias . '.branch_id');
    }

    private function sales()
    {
        return $this->b('sales')->where('deleted', 'No')->where('status', 'Active');
    }

    private function between($query, string $column, Carbon $from, Carbon $to)
    {
        return $query->whereBetween($column, [$from->toDateString(), $to->toDateString()]);
    }

    private function totals(Carbon $from, Carbon $to): array
    {
        $sales = $this->between($this->sales(), 'sale_date', $from, $to)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(grand_total), 0) AS total, COALESCE(SUM(vat_amount), 0) AS vat, COALESCE(SUM(due_amount), 0) AS due')->first();

        $cost = (float) $this->between(
            BranchController::scope(DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id'), 's.branch_id')
                ->where('s.deleted', 'No')->where('s.status', 'Active')->where('si.deleted', 'No'),
            's.sale_date', $from, $to
        )->sum(DB::raw('si.qty * si.purchase_price'));

        $returns = (float) $this->b('sale_returns')->where('deleted', 'No')->where('status', 'Active')
            ->whereBetween(DB::raw('DATE(datetime)'), [$from->toDateString(), $to->toDateString()])->sum('grand_total');

        $purchases = (float) $this->between(
            $this->b('purchases')->where('deleted', 'No')->where('status', 'Active'), 'purchase_date', $from, $to
        )->sum('grand_total');

        $expenses = (float) $this->between($this->b('expenses')->where('deleted', 'No'), 'expense_date', $from, $to)->sum('total_amount')
            + (float) $this->between($this->b('bills')->where('deleted', 'No'), 'bill_date', $from, $to)->sum('grand_total')
            + (float) $this->between($this->b('payroll_runs')->where('run_status', 'Posted'), 'run_date', $from, $to)
                ->sum(DB::raw('total_net + total_pf + total_loan'));

        $collected = (float) $this->b('payments')->where('deleted', 'No')->where('status', 'Active')
            ->whereIn('party_type', ['Customer', 'Walk-In Customer'])->where('payment_type', 'like', '%Receive%')
            ->whereBetween(DB::raw('DATE(datetime)'), [$from->toDateString(), $to->toDateString()])->sum('debit');

        $sold = (float) $sales->total;
        $netSold = $sold - (float) $sales->vat; // VAT collected is owed to the government, not income

        return [
            'sales'     => $sold,
            'orders'    => (int) $sales->n,
            'avg'       => $sales->n ? $sold / $sales->n : 0.0,
            'returns'   => $returns,
            'cost'      => $cost,
            'profit'    => $netSold - $cost,
            'margin'    => $netSold > 0 ? ($netSold - $cost) / $netSold * 100 : null,
            'net'       => $netSold - $cost - $expenses,
            'purchases' => $purchases,
            'expenses'  => $expenses,
            'collected' => $collected,
        ];
    }

    private function kpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $now  = $this->totals($from, $to);
        $prev = $this->totals($prevFrom, $prevTo);

        $change = function (string $key) use ($now, $prev) {
            if (!$prev[$key]) {
                return null;
            }

            return ($now[$key] - $prev[$key]) / abs($prev[$key]) * 100;
        };

        foreach (['sales', 'orders', 'avg', 'profit', 'purchases', 'expenses', 'collected', 'net'] as $key) {
            $now['change'][$key] = $change($key);
        }
        $now['prev'] = $prev;

        return $now;
    }

    private function todaySnapshot(): array
    {
        $today = Carbon::today();
        $t = $this->totals($today, $today->copy()->endOfDay());
        $yesterday = (float) $this->sales()->where('sale_date', $today->copy()->subDay()->toDateString())->sum('grand_total');

        return $t + [
            'change' => ['sales' => $yesterday ? ($t['sales'] - $yesterday) / $yesterday * 100 : null],
            'items' => (float) BranchController::scope(DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id'), 's.branch_id')
                ->where('s.deleted', 'No')->where('s.status', 'Active')->where('si.deleted', 'No')
                ->where('s.sale_date', $today->toDateString())->sum('si.qty'),
        ];
    }

    /** Last 12 months, oldest first: sales and purchases. */
    private function monthlyTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(11);

        $sales = $this->sales()->where('sale_date', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(sale_date, '%Y-%m') AS ym, SUM(grand_total) AS total")->groupBy('ym')->pluck('total', 'ym');

        $purchases = $this->b('purchases')->where('deleted', 'No')->where('status', 'Active')
            ->where('purchase_date', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(purchase_date, '%Y-%m') AS ym, SUM(grand_total) AS total")->groupBy('ym')->pluck('total', 'ym');

        $byMonth = fn ($table, $col, $sum, $where) => $this->b($table)->where($where)->where($col, '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT($col, '%Y-%m') AS ym, SUM($sum) AS total")->groupBy('ym')->pluck('total', 'ym');
        $expenseSets = [
            $byMonth('expenses', 'expense_date', 'total_amount', ['deleted' => 'No']),
            $byMonth('bills', 'bill_date', 'grand_total', ['deleted' => 'No']),
            $byMonth('payroll_runs', 'run_date', 'total_net + total_pf + total_loan', ['run_status' => 'Posted']),
        ];
        $collected = $this->b('payments')->where('deleted', 'No')->where('status', 'Active')
            ->whereIn('party_type', ['Customer', 'Walk-In Customer'])->where('payment_type', 'like', '%Receive%')
            ->where('datetime', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(datetime, '%Y-%m') AS ym, SUM(debit) AS total")->groupBy('ym')->pluck('total', 'ym');

        $months = [];
        for ($m = $start->copy(); $m->lte(Carbon::now()); $m->addMonth()) {
            $ym = $m->format('Y-m');
            $months[] = [
                'label'     => $m->format('M'),
                'title'     => $m->format('F Y'),
                'sales'     => (float) ($sales[$ym] ?? 0),
                'purchases' => (float) ($purchases[$ym] ?? 0),
                'expenses'  => array_sum(array_map(fn ($set) => (float) ($set[$ym] ?? 0), $expenseSets)),
                'collected' => (float) ($collected[$ym] ?? 0),
            ];
        }

        return $months;
    }

    /** Daily sales for the last 30 days. */
    private function dailySales(): array
    {
        $start = Carbon::today()->subDays(29);

        $rows = $this->sales()->where('sale_date', '>=', $start->toDateString())
            ->selectRaw('sale_date AS d, SUM(grand_total) AS total, COUNT(*) AS n')->groupBy('sale_date')->get()->keyBy('d');

        $days = [];
        foreach (CarbonPeriod::create($start, Carbon::today()) as $day) {
            $row = $rows[$day->toDateString()] ?? null;
            $days[] = [
                'date'  => $day->format('d M'),
                'total' => (float) ($row->total ?? 0),
                'count' => (int) ($row->n ?? 0),
            ];
        }

        return $days;
    }

    private function salesMix(Carbon $from, Carbon $to): array
    {
        $rows = $this->between($this->sales(), 'sale_date', $from, $to)
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

    /** Liquid Asset balances: payment methods (layer 2) with their accounts (layer 3). */
    /**
     * Cash & bank of the logged-in branch: each ledger's opening balance + this branch's money movements
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

        $methods = collect($balance)->groupBy('method')->map(fn ($g, $name) => [
            'name'     => $name,
            'balance'  => round($g->sum('balance'), 2),
            'accounts' => $g->map(fn ($a) => ['name' => $a['name'], 'balance' => round($a['balance'], 2)])->values()->all(),
        ])->values()->all();

        return [
            'methods' => $methods,
            'total'   => round(array_sum(array_column($methods, 'balance')), 2),
        ];
    }

    /** Receivables and payables from the party ledger (same rule as PartyDueTrait). */
    private function dues(): array
    {
        $parties = DB::table('parties as p')->where('p.deleted', 'No')
            ->leftJoin('payments as pm', function ($j) {
                $j->on('pm.party_id', '=', 'p.id')->where('pm.deleted', 'No')->where('pm.status', 'Active')
                    ->where('pm.branch_id', BranchController::current())
                    // party_id also holds employee ids on employee expense rows
                    ->where(fn ($q) => $q->whereNull('pm.party_type')->orWhere('pm.party_type', '<>', 'Employee'));
            })
            ->groupBy('p.id', 'p.name', 'p.phone', 'p.type', 'p.opening_balance', 'p.opening_balance_type')
            ->selectRaw('p.id, p.name, p.phone, p.type, p.opening_balance, p.opening_balance_type,
                COALESCE(SUM(pm.debit), 0) AS debit, COALESCE(SUM(pm.credit), 0) AS credit')
            ->get()
            ->map(function ($p) {
                $p->opening_balance = BranchController::isMainBranch() ? (float) $p->opening_balance : 0;   // opening belongs to the main branch
                if ($p->opening_balance_type === 'Advance') {
                    $p->opening_balance = -$p->opening_balance;
                }
                $p->due = $p->type === 'Supplier'
                    ? (float) $p->opening_balance + $p->debit - $p->credit
                    : (float) $p->opening_balance + $p->credit - $p->debit;

                return $p;
            });

        $customers = $parties->where('type', '!=', 'Supplier')->where('due', '>', 0.004);
        $suppliers = $parties->where('type', 'Supplier')->where('due', '>', 0.004);

        return [
            'receivable'     => (float) $customers->sum('due'),
            'payable'        => (float) $suppliers->sum('due'),
            'customerCount'  => $customers->count(),
            'supplierCount'  => $suppliers->count(),
            'topCustomers'   => $customers->sortByDesc('due')->take(5)->values()->all(),
            'topSuppliers'   => $suppliers->sortByDesc('due')->take(5)->values()->all(),
        ];
    }

    private function topProducts(Carbon $from, Carbon $to): array
    {
        return $this->between(
            BranchController::scope(DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id'), 's.branch_id')
                ->join('products as p', 'p.id', '=', 'si.product_id')
                ->where('s.deleted', 'No')->where('s.status', 'Active')->where('si.deleted', 'No'),
            's.sale_date', $from, $to
        )
            ->groupBy('p.id', 'p.name', 'p.image')
            ->selectRaw('p.id, p.name, p.image, SUM(si.qty) AS qty, SUM(si.total) AS revenue,
                SUM(si.total - si.qty * si.purchase_price) AS profit')
            ->orderByDesc('revenue')->limit(5)->get()
            ->map(function ($r) {
                $r->image_url = $r->image ? asset('uploads/products/' . $r->image) : null;

                return $r;
            })->all();
    }

    private function stockHealth(): array
    {
        $products = DB::table('products as p')->where('p.deleted', 'No')->where('p.status', 'Active')
            ->leftJoin('stocks as st', function ($j) {
                $j->on('st.product_id', '=', 'p.id')->where('st.deleted', 'No')->where('st.status', 'Active');
                $branch = BranchController::current();
                $branch ? $j->where('st.branch_id', $branch) : $j->whereIn('st.branch_id', BranchController::allowedIds() ?: [0]);
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
            'alerts'   => $out->concat($low->sortBy('stock'))->take(6)->values()->all(),
        ];
    }

    private function recentSales(): array
    {
        return $this->b('sales as s')->where('s.deleted', 'No')->where('s.status', 'Active')
            ->leftJoin('parties as p', 'p.id', '=', 's.customer_id')
            ->orderByDesc('s.sale_date')->orderByDesc('s.id')->limit(6)
            ->get(['s.id', 's.invoice_no', 's.sale_type', 's.sale_date', 's.grand_total', 's.due_amount', 'p.name as customer'])
            ->all();
    }

    /** Latest money events across modules, newest first. */
    private function activity(): array
    {
        $take = 8;
        $rows = collect();

        $rows = $rows->concat($this->b('sales as s')->leftJoin('parties as p', 'p.id', '=', 's.customer_id')
            ->where('s.deleted', 'No')->where('s.status', 'Active')->orderByDesc('s.id')->limit($take)
            ->get(['s.created_date as at', 's.sale_date as d', 's.invoice_no as ref', 's.grand_total as amount', 's.sale_type', 'p.name as who'])
            ->map(fn ($r) => ['kind' => 'sale', 'icon' => 'bi-bag-check', 'title' => $r->sale_type . ' sale', 'ref' => $r->ref,
                'who' => $r->who ?: 'Walk-In Customer', 'amount' => (float) $r->amount, 'in' => true, 'at' => $r->at ?: $r->d]));

        $rows = $rows->concat($this->b('purchases as s')->leftJoin('parties as p', 'p.id', '=', 's.supplier_id')
            ->where('s.deleted', 'No')->where('s.status', 'Active')->orderByDesc('s.id')->limit($take)
            ->get(['s.created_date as at', 's.purchase_date as d', 's.invoice_no as ref', 's.grand_total as amount', 'p.name as who'])
            ->map(fn ($r) => ['kind' => 'purchase', 'icon' => 'bi-bag-plus', 'title' => 'Purchase', 'ref' => $r->ref,
                'who' => $r->who ?: '-', 'amount' => (float) $r->amount, 'in' => false, 'at' => $r->at ?: $r->d]));

        $rows = $rows->concat($this->b('payments as m')->leftJoin('parties as p', 'p.id', '=', 'm.party_id')
            ->where('m.deleted', 'No')->where('m.status', 'Active')
            ->whereIn('m.payment_type', ['Payment Receive', 'Payment', 'Bill Payment'])->orderByDesc('m.id')->limit($take)
            ->get(['m.datetime as at', 'm.code as ref', 'm.debit', 'm.credit', 'm.payment_type', 'p.name as who'])
            ->map(fn ($r) => ['kind' => $r->payment_type === 'Payment Receive' ? 'receipt' : 'payment',
                'icon' => $r->payment_type === 'Payment Receive' ? 'bi-cash-coin' : 'bi-send', 'title' => $r->payment_type === 'Payment Receive' ? 'Payment received' : $r->payment_type,
                'ref' => $r->ref, 'who' => $r->who ?: '-', 'amount' => (float) $r->debit + (float) $r->credit,
                'in' => $r->payment_type === 'Payment Receive', 'at' => $r->at]));

        $rows = $rows->concat($this->b('expenses')->where('deleted', 'No')->orderByDesc('id')->limit($take)
            ->get(['created_date as at', 'expense_date as d', 'expense_no as ref', 'total_amount as amount', 'remarks'])
            ->map(fn ($r) => ['kind' => 'expense', 'icon' => 'bi-receipt', 'title' => 'Expense', 'ref' => $r->ref,
                'who' => $r->remarks ?: '-', 'amount' => (float) $r->amount, 'in' => false, 'at' => $r->at ?: $r->d]));

        $rows = $rows->concat($this->b('salary_payments')->where('payment_status', 'Posted')->orderByDesc('id')->limit($take)
            ->get(['created_date as at', 'payment_date as d', 'payment_no as ref', 'total_paid as amount', 'month', 'employee_count'])
            ->map(fn ($r) => ['kind' => 'salary', 'icon' => 'bi-people', 'title' => 'Salary paid', 'ref' => $r->ref,
                'who' => Carbon::parse($r->month)->format('F Y') . ' · ' . $r->employee_count . ' employee(s)', 'amount' => (float) $r->amount, 'in' => false, 'at' => $r->at ?: $r->d]));

        $rows = $rows->concat($this->b('employee_loans as l')->join('employees as e', 'e.id', '=', 'l.employee_id')
            ->where('l.deleted', 'No')->where('l.loan_status', '<>', 'Cancelled')->orderByDesc('l.id')->limit($take)
            ->get(['l.created_date as at', 'l.issue_date as d', 'l.loan_no as ref', 'l.amount', 'l.loan_type', 'e.name as who'])
            ->map(fn ($r) => ['kind' => 'loan', 'icon' => 'bi-piggy-bank', 'title' => $r->loan_type . ' given', 'ref' => $r->ref,
                'who' => $r->who, 'amount' => (float) $r->amount, 'in' => false, 'at' => $r->at ?: $r->d]));

        return $rows->filter(fn ($r) => $r['at'])->sortByDesc('at')->take($take)
            ->map(fn ($r) => $r + ['when' => Carbon::parse($r['at'])])->values()->all();
    }

    private function pipeline(): array
    {
        $quotes = $this->b('sale_quotations')->where('deleted', 'No')
            ->selectRaw('quotation_status, COUNT(*) AS n, SUM(grand_total) AS total')->groupBy('quotation_status')->get()->keyBy('quotation_status');

        $orders = $this->b('sale_orders')->where('deleted', 'No')->whereIn('order_status', ['Pending', 'Confirmed', 'Partial'])
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(grand_total), 0) AS total, COALESCE(SUM(due_amount), 0) AS due')->first();

        $open = collect(['Draft', 'Sent'])->sum(fn ($s) => (int) ($quotes[$s]->n ?? 0));

        return [
            'quoteOpen'      => $open,
            'quoteOpenValue' => collect(['Draft', 'Sent'])->sum(fn ($s) => (float) ($quotes[$s]->total ?? 0)),
            'quoteAccepted'  => (int) ($quotes['Accepted']->n ?? 0),
            'quoteTotal'     => (int) $quotes->sum('n'),
            'orderOpen'      => (int) $orders->n,
            'orderValue'     => (float) $orders->total,
            'orderDue'       => (float) $orders->due,
        ];
    }

    private function hr(): array
    {
        $today = Carbon::today()->toDateString();

        $lastPayment = $this->b('salary_payments')->where('payment_status', 'Posted')
            ->orderByDesc('month')->first(['month', 'payment_date', 'total_paid', 'employee_count']);

        // This month's payroll: processed lines vs everyone employed.
        $month = Carbon::now()->startOfMonth()->toDateString();
        $run = BranchController::scope(DB::table('payroll_run_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id'), 'r.branch_id')
            ->where('r.month', $month)->where('r.run_status', 'Posted')
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(i.net_payable), 0) AS net, COALESCE(SUM(i.paid_amount), 0) AS paid')->first();
        $loans = $this->b('employee_loans')->where('deleted', 'No')->where('loan_status', 'Running')
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(amount - recovered), 0) AS balance')->first();

        return [
            'payrollMonth' => ['processed' => (int) $run->n, 'net' => (float) $run->net, 'paid' => (float) $run->paid,
                'due' => round((float) $run->net - (float) $run->paid, 2)],
            'loans'     => ['count' => (int) $loans->n, 'balance' => (float) $loans->balance],
            'employees' => $this->b('employees')->where('deleted', 'No')->where('status', 'Active')
                ->where(fn ($q) => $q->whereNull('resign_date')->orWhere('resign_date', '>', $today))->count(),
            'onLeave'   => BranchController::scope(DB::table('leave_applications as la')->join('employees as e', 'e.id', '=', 'la.employee_id'), 'e.branch_id')->where('la.deleted', 'No')->where('la.leave_status', 'Approved')
                ->where('la.from_date', '<=', $today)->where('la.to_date', '>=', $today)->count(),
            'pending'   => BranchController::scope(DB::table('leave_applications as la')->join('employees as e', 'e.id', '=', 'la.employee_id'), 'e.branch_id')->where('la.deleted', 'No')->where('la.leave_status', 'Pending')->count(),
            'payroll'   => (float) $this->b('employees')->where('deleted', 'No')->where('status', 'Active')->sum('gross_salary'),
            'lastPayment' => $lastPayment,
            'holidays'  => DB::table('holidays')->where('deleted', 'No')->where('to_date', '>=', $today)
                ->orderBy('from_date')->limit(3)->get(['title', 'from_date', 'to_date', 'type'])->all(),
        ];
    }
}
