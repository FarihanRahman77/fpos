<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Roles & permissions.
 *
 * A permission is module × action, slug "<module>.<action>" (e.g. sales.view, sales.create). MODULES lists them,
 * ROUTE_MAP says which permission each route needs, and the CheckPermission middleware (web group) enforces it.
 * The Superadmin role (slug "superadmin") always has every permission. Routes in OPEN need only a login;
 * routes in neither list are allowed and logged once a day, so gaps can be closed.
 *
 * Views use @perm('sales.create') … @endperm (or PermissionController::allows()) to hide what the user can't use.
 */
class PermissionController extends Controller
{
    public const SUPERADMIN = 'superadmin';

    public const ACTIONS = ['view' => 'View', 'create' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete'];

    /** key => [label, group, actions] — one row of the role permission grid each. */
    public const MODULES = [
        'dashboard'           => ['Dashboard', 'General', ['view']],
        'pos'                 => ['POS', 'Sales', ['view', 'create']],
        'sales'               => ['Sales', 'Sales', ['view', 'create', 'edit', 'delete']],
        'sale_returns'        => ['Sale returns', 'Sales', ['view', 'create', 'delete']],
        'quotations'          => ['Quotations', 'Sales', ['view', 'create', 'edit', 'delete']],
        'sale_orders'         => ['Sale orders', 'Sales', ['view', 'create', 'edit', 'delete']],
        'purchases'           => ['Purchases', 'Purchase', ['view', 'create', 'edit', 'delete']],
        'purchase_returns'    => ['Purchase returns', 'Purchase', ['view', 'create', 'delete']],
        'products'            => ['Products & masters (categories, brands, units, attributes, barcodes)', 'Inventory', ['view', 'create', 'edit', 'delete']],
        'stock'               => ['Stock (transfers, adjustments, batches)', 'Inventory', ['view', 'create', 'edit', 'delete']],
        'parties'             => ['Customers & suppliers', 'CRM', ['view', 'create', 'edit', 'delete']],
        'investors'           => ['Investors', 'CRM', ['view', 'create', 'edit', 'delete']],
        'loyalty_vat'         => ['Loyalty, VAT & credit', 'CRM', ['view', 'edit']],
        'payments'            => ['Payments & discounts', 'Payments', ['view', 'create', 'delete']],
        'payment_receive'     => ['Payment receive', 'Payments', ['view', 'create', 'delete']],
        'expenses'            => ['Expenses', 'Accounts', ['view', 'create', 'delete']],
        'bills'               => ['Bills', 'Accounts', ['view', 'create', 'delete']],
        'journals'            => ['Journals', 'Accounts', ['view', 'create', 'delete']],
        'transfers'           => ['Amount transfer', 'Accounts', ['view', 'create', 'delete']],
        'suspense'            => ['Suspense (advances, investments, capital)', 'Accounts', ['view', 'create', 'edit', 'delete']],
        'chart_of_accounts'   => ['Chart of accounts', 'Accounts', ['view', 'create', 'edit', 'delete']],
        'statements'          => ['Accounting statements (P&L, trial balance, balance sheet)', 'Reports', ['view']],
        'year_end'            => ['Year-end closing', 'Reports', ['view', 'create', 'delete']],
        'reports'             => ['Reports (ledgers, Reports Center, budgets)', 'Reports', ['view', 'edit']],
        'cash_closing'        => ['Daily cash closing', 'Reports', ['view', 'create', 'delete']],
        'employees'           => ['Employees', 'Payroll & HR', ['view', 'create', 'edit', 'delete']],
        'payroll_setup'       => ['Payroll setup', 'Payroll & HR', ['view', 'create', 'edit', 'delete']],
        'payroll'             => ['Payroll run, salary sheet, payments & payslips', 'Payroll & HR', ['view', 'create', 'delete']],
        'payroll_adjustments' => ['Bonus & adjustments', 'Payroll & HR', ['view', 'create', 'edit', 'delete']],
        'loans'               => ['Loans & advances', 'Payroll & HR', ['view', 'create', 'edit', 'delete']],
        'leave'               => ['Leave (applications, ledger, setup)', 'Payroll & HR', ['view', 'create', 'edit', 'delete']],
        'attendance'          => ['Attendance', 'Payroll & HR', ['view', 'create', 'edit', 'delete']],
        'holidays'            => ['Holiday calendar', 'Payroll & HR', ['view', 'create', 'edit', 'delete']],
        'users'               => ['Users', 'Administration', ['view', 'create', 'edit', 'delete']],
        'roles'               => ['Roles & permissions', 'Administration', ['view', 'create', 'edit', 'delete']],
        'branches'            => ['Branches', 'Administration', ['view', 'create', 'edit', 'delete']],
        'settings'            => ['Settings', 'Administration', ['view', 'edit']],
        'system'              => ['System (backups, email & SMS, activity log)', 'Administration', ['view', 'create', 'edit', 'delete']],
    ];

    /** Routes that only need a signed-in user (sign-in/out, own profile, shared look-ups). */
    public const OPEN = [
        'login', 'logout', 'customLogin', 'password.*', 'verification.*', 'profile.*', 'sanctum.*', 'ignition.*',
        'admin.users.profile', 'admin.getCoaChilds', 'admin.coa.ledgers', 'admin.payroll-setup.options',
    ];

    /**
     * Route-name pattern (Str::is) => permission. First match wins. The value is a permission slug, a list of slugs
     * (any one is enough), "~module" (module.create without an `id` in the request, module.edit with one),
     * or "@payment.<action>" / "@report" (resolved from the payment type / report key).
     */
    public const ROUTE_MAP = [
        'dashboard' => 'dashboard.view',

        // ---- POS & sales ----
        'sales.pos' => 'pos.view',
        'sales.store' => ['sales.create', 'pos.create'],
        'sales.create' => 'sales.create',
        'sales.delete' => 'sales.delete',
        'sales.invoice.pdf' => ['sales.view', 'pos.view'],
        'sales.index' => 'sales.view',
        'sales.list' => 'sales.view',
        'sales.customer.*' => ['pos.view', 'sales.view', 'sale_orders.view', 'quotations.view', 'sale_returns.view'],
        'sales.products.*' => ['pos.view', 'sales.view', 'sale_orders.view', 'quotations.view', 'sale_returns.view'],
        'sales.returns.*' => 'sale_returns.view',
        'sales.return.view' => 'sale_returns.view',
        'sales.return.invoice.pdf' => 'sale_returns.view',
        'sales.return.delete' => 'sale_returns.delete',
        'sales.return.*' => 'sale_returns.create',
        'sales.order.invoice.pdf' => 'sale_orders.view',
        'sales.orders.index' => 'sale_orders.view',
        'sales.orders.list' => 'sale_orders.view',
        'sales.orders.create' => 'sale_orders.create',
        'sales.orders.store' => 'sale_orders.create',
        'sales.orders.deliver*' => 'sale_orders.edit',
        'sales.orders.cancel' => 'sale_orders.delete',
        'sales.orders.delete' => 'sale_orders.delete',
        'admin.quotations.create' => 'quotations.create',
        'admin.quotations.duplicate' => 'quotations.create',
        'admin.quotations.convert' => 'quotations.edit',
        'admin.quotations.store' => '~quotations',
        'admin.quotations.edit' => 'quotations.edit',
        'admin.quotations.status' => 'quotations.edit',
        'admin.quotations.send' => 'quotations.edit',
        'admin.quotations.share' => 'quotations.edit',
        'admin.quotations.delete' => 'quotations.delete',
        'admin.quotations.*' => 'quotations.view',

        // ---- Purchases ----
        'purchases.supplier.*' => ['purchases.view', 'purchase_returns.view'],
        'purchases.products.*' => ['purchases.view', 'purchase_returns.view'],
        'purchases.return.delete' => 'purchase_returns.delete',
        'purchases.return.page' => 'purchase_returns.create',
        'purchases.return.data' => 'purchase_returns.create',
        'purchases.return.store' => 'purchase_returns.create',
        'purchases.return.*' => 'purchase_returns.view',
        'purchases.create' => 'purchases.create',
        'purchases.store' => 'purchases.create',
        'purchases.edit' => 'purchases.edit',
        'purchases.update' => 'purchases.edit',
        'purchases.delete' => 'purchases.delete',
        'purchases.*' => 'purchases.view',

        // ---- Products & masters ----
        'products.store' => 'products.create',
        'products.generate.*' => ['products.create', 'products.edit'],
        'products.edit' => 'products.edit',
        'products.update' => 'products.edit',
        'products.status' => 'products.edit',
        'products.delete' => 'products.delete',
        'products.*' => 'products.view',
        'admin.category.save' => '~products', 'admin.brand.save' => '~products', 'admin.unit.save' => '~products', 'admin.attribute.save' => '~products',
        'admin.attribute_types.store' => 'products.create',
        'admin.attribute_types.update' => 'products.edit',
        'admin.category.edit' => 'products.edit', 'admin.brand.edit' => 'products.edit', 'admin.unit.edit' => 'products.edit',
        'admin.attribute.edit' => 'products.edit', 'admin.attribute_types.edit' => 'products.edit',
        'admin.category.status' => 'products.edit', 'admin.brand.status' => 'products.edit', 'admin.unit.status' => 'products.edit',
        'admin.attribute.status' => 'products.edit', 'admin.attribute_types.status' => 'products.edit',
        'admin.category.delete' => 'products.delete', 'admin.brand.delete' => 'products.delete', 'admin.unit.delete' => 'products.delete',
        'admin.attribute.delete' => 'products.delete', 'admin.attribute_types.delete' => 'products.delete',
        'admin.category.*' => 'products.view', 'admin.brand.*' => 'products.view', 'admin.unit.*' => 'products.view',
        'admin.attribute.*' => 'products.view', 'admin.attribute_types.*' => 'products.view', 'admin.barcode.*' => 'products.view',

        // ---- Stock ----
        'admin.stock.transfers.store' => 'stock.create',
        'admin.stock.adjustments.store' => 'stock.create',
        'admin.stock.transfers.receive' => 'stock.edit',
        'admin.stock.batches.expiry' => 'stock.edit',
        'admin.stock.*.cancel' => 'stock.delete',
        'admin.stock.*' => 'stock.view',

        // ---- Parties, loyalty, VAT ----
        // Investors are a party type with their own permissions (investors.*); see resolve('@party.…').
        'party.store' => '@party.create',
        'party.update' => '@party.edit',
        'party.toggle_status' => '@party.edit',
        'party.delete' => '@party.delete',
        'party.*' => '@party.view',
        'admin.system.sms.due.send' => 'parties.edit',
        'admin.system.sms.due*' => 'parties.view',
        'admin.customers.loyalty.adjust' => 'loyalty_vat.edit',
        'admin.customers.settings.save' => 'loyalty_vat.edit',
        'admin.customers.*' => 'loyalty_vat.view',

        // ---- Payments (type decides payments.* vs payment_receive.*) ----
        'admin.payment.due' => ['payments.view', 'payment_receive.view', 'pos.view', 'sales.view', 'purchases.view'],
        'admin.payment.store' => '@payment.create',
        'admin.payment.delete' => '@payment.delete',
        'admin.payment.*' => '@payment.view',

        // ---- Accounts ----
        'admin.expense.store' => 'expenses.create',
        'admin.expense.delete' => 'expenses.delete',
        'admin.expense.*' => 'expenses.view',
        'admin.bill.store' => 'bills.create',
        'admin.bill.payment.store' => 'bills.create',
        'admin.bill.delete' => 'bills.delete',
        'admin.bill.payment.delete' => 'bills.delete',
        'admin.bill.*' => 'bills.view',
        'admin.journal.store' => 'journals.create',
        'admin.journal.delete' => 'journals.delete',
        'admin.journal.*' => 'journals.view',
        'admin.transfer.store' => 'transfers.create',
        'admin.transfer.delete' => 'transfers.delete',
        'admin.transfer.*' => 'transfers.view',
        'admin.suspense.store' => '~suspense',
        'admin.suspense.delete' => 'suspense.delete',
        'admin.suspense.*' => 'suspense.view',
        'admin.chart-of-accounts.store' => 'chart_of_accounts.create',
        'admin.chart-of-accounts.update' => 'chart_of_accounts.edit',
        'admin.chart-of-accounts.status' => 'chart_of_accounts.edit',
        'admin.chart-of-accounts.destroy' => 'chart_of_accounts.delete',
        'admin.chart-of-accounts.*' => 'chart_of_accounts.view',

        // ---- Statements, reports, cash closing ----
        'admin.accounting.fiscal-years.store' => 'year_end.create',
        'admin.accounting.fiscal-years.close' => 'year_end.create',
        'admin.accounting.fiscal-years.reopen' => 'year_end.delete',
        'admin.accounting.fiscal-years.delete' => 'year_end.delete',
        'admin.accounting.fiscal-years*' => 'year_end.view',
        'admin.accounting.*' => 'statements.view',
        'admin.reports.salary-sheet*' => 'payroll.view',
        'admin.reports.pf-ledger*' => 'payroll.view',
        'admin.reports.*' => 'reports.view',
        'admin.business-reports.closing.save' => 'cash_closing.create',
        'admin.business-reports.closing.clear' => 'cash_closing.delete',
        'admin.business-reports.budget.save' => 'reports.edit',
        'admin.business-reports.index' => ['reports.view', 'cash_closing.view'],
        'admin.business-reports.*' => '@report',

        // ---- Payroll & HR ----
        'admin.employee.search' => ['employees.view', 'payroll.view', 'payroll_adjustments.view', 'loans.view', 'leave.view', 'attendance.view'],
        'admin.employee.steps' => ['employees.create', 'employees.edit'],
        'admin.employee.store' => '~employees',
        'admin.employee.status' => 'employees.edit',
        'admin.employee.delete' => 'employees.delete',
        'admin.employee.*' => 'employees.view',
        'admin.payroll-setup.save' => '~payroll_setup',
        'admin.payroll-setup.edit' => 'payroll_setup.edit',
        'admin.payroll-setup.increment' => 'payroll_setup.edit',
        'admin.payroll-setup.settings' => 'payroll_setup.edit',
        'admin.payroll-setup.status' => 'payroll_setup.edit',
        'admin.payroll-setup.delete' => 'payroll_setup.delete',
        'admin.payroll-setup.*' => 'payroll_setup.view',
        'admin.payroll-runs.store' => 'payroll.create',
        'admin.payroll-runs.reverse' => 'payroll.delete',
        'admin.salary-payments.store' => 'payroll.create',
        'admin.salary-payments.cancel' => 'payroll.delete',
        'admin.payslips.email*' => 'payroll.create',
        'admin.payroll-runs.*' => 'payroll.view',
        'admin.salary-payments.*' => 'payroll.view',
        'admin.payslips.*' => 'payroll.view',
        'admin.payroll-adjustments.*.save' => '~payroll_adjustments',
        'admin.payroll-adjustments.*.delete' => 'payroll_adjustments.delete',
        'admin.payroll-adjustments.*' => 'payroll_adjustments.view',
        'admin.employee-loans.store' => 'loans.create',
        'admin.employee-loans.repay' => 'loans.edit',
        'admin.employee-loans.reschedule' => 'loans.edit',
        'admin.employee-loans.cancel' => 'loans.delete',
        'admin.employee-loans.*' => 'loans.view',
        'admin.leave.calendar.holiday.delete' => 'holidays.delete',
        'admin.leave.calendar.holiday' => '~holidays',
        'admin.leave.calendar.weekend' => 'holidays.edit',
        'admin.leave.calendar.copy' => 'holidays.create',
        'admin.leave.calendar.*' => 'holidays.view',
        'admin.leave.store' => 'leave.create',
        'admin.leave.decide' => 'leave.edit',
        'admin.leave.adjustment' => 'leave.edit',
        'admin.leave.setup.save' => '~leave',
        'admin.leave.setup.rules.*' => 'leave.edit',
        'admin.leave.setup.edit' => 'leave.edit',
        'admin.leave.setup.status' => 'leave.edit',
        'admin.leave.*delete' => 'leave.delete',
        'admin.leave.*' => 'leave.view',
        'admin.attendance.daily.save' => ['attendance.create', 'attendance.edit'],
        'admin.attendance.import.*' => 'attendance.create',
        'admin.attendance.salary.send' => 'attendance.create',
        'admin.attendance.settings.save' => 'attendance.edit',
        'admin.attendance.shifts.save' => '~attendance',
        'admin.attendance.devices.save' => '~attendance',
        'admin.attendance.devices.sync' => 'attendance.edit',
        'admin.attendance.devices.test' => 'attendance.edit',
        'admin.attendance.*.delete' => 'attendance.delete',
        'admin.attendance.*' => 'attendance.view',

        // ---- Administration ----
        'admin.users.store' => 'users.create',
        'admin.users.edit' => 'users.edit',
        'admin.users.update' => 'users.edit',
        'admin.users.delete' => 'users.delete',
        'admin.users.*' => 'users.view',
        'admin.roles.store' => 'roles.create',
        'admin.permissions.store' => 'roles.create',
        'admin.roles.edit' => 'roles.edit',
        'admin.roles.update' => 'roles.edit',
        'admin.roles.permissions.update' => 'roles.edit',
        'admin.permissions.edit' => 'roles.edit',
        'admin.permissions.update' => 'roles.edit',
        'admin.roles.delete' => 'roles.delete',
        'admin.permissions.delete' => 'roles.delete',
        'admin.roles.*' => 'roles.view',
        'admin.permissions.*' => 'roles.view',
        'admin.branches.save' => '~branches',
        'admin.branches.access*' => 'branches.edit',
        'admin.branches.main' => 'branches.edit',
        'admin.branches.delete' => 'branches.delete',
        'admin.branches.*' => 'branches.view',
        'admin.settings.update' => 'settings.edit',
        'admin.settings.theme.save' => 'settings.edit',
        'admin.settings.theme.reset' => 'settings.edit',
        'admin.settings.*' => 'settings.view',
        'admin.system.backups.run' => 'system.create',
        'admin.system.backups.settings' => 'system.edit',
        'admin.system.backups.delete' => 'system.delete',
        'admin.system.email-sms.*' => 'system.edit',
        'admin.system.activity.retention' => 'system.edit',
        'admin.system.activity.prune' => 'system.delete',
        'admin.system.*' => 'system.view',
    ];

    /** Presets offered on the role permission screen: module => actions ('*' = every action of the module). */
    public const PRESETS = [
        'cashier' => ['Cashier', [
            'pos' => '*', 'sales' => ['view', 'create'], 'sale_returns' => ['view', 'create'],
            'parties' => ['view', 'create'], 'payment_receive' => ['view', 'create'], 'cash_closing' => ['view', 'create'],
        ]],
        'storekeeper' => ['Storekeeper', [
            'dashboard' => '*', 'products' => '*', 'stock' => '*', 'purchases' => '*', 'purchase_returns' => '*',
            'parties' => ['view', 'create'], 'reports' => ['view'],
        ]],
        'accountant' => ['Accountant', [
            'dashboard' => '*', 'sales' => ['view'], 'sale_returns' => ['view'], 'purchases' => ['view'], 'purchase_returns' => ['view'],
            'parties' => ['view'], 'loyalty_vat' => ['view'], 'payments' => '*', 'payment_receive' => '*', 'expenses' => '*', 'bills' => '*',
            'journals' => '*', 'transfers' => '*', 'chart_of_accounts' => '*', 'statements' => '*', 'year_end' => '*', 'reports' => '*',
            'cash_closing' => '*', 'payroll' => ['view', 'create'], 'investors' => ['view'], 'suspense' => '*',
        ]],
        'hr' => ['HR & payroll', [
            'dashboard' => '*', 'employees' => '*', 'payroll_setup' => '*', 'payroll' => '*', 'payroll_adjustments' => '*',
            'loans' => '*', 'leave' => '*', 'attendance' => '*', 'holidays' => '*',
        ]],
        'branch_manager' => ['Branch manager', [
            'dashboard' => '*', 'pos' => '*', 'sales' => '*', 'sale_returns' => '*', 'quotations' => '*', 'sale_orders' => '*',
            'purchases' => '*', 'purchase_returns' => '*', 'products' => '*', 'stock' => '*', 'parties' => '*', 'loyalty_vat' => ['view'],
            'payments' => '*', 'payment_receive' => '*', 'expenses' => '*', 'bills' => '*', 'journals' => ['view'], 'transfers' => '*',
            'suspense' => '*', 'investors' => ['view'], 'chart_of_accounts' => ['view'], 'statements' => '*', 'reports' => '*', 'cash_closing' => '*', 'employees' => '*',
            'payroll' => ['view'], 'payroll_adjustments' => '*', 'loans' => ['view'], 'leave' => '*', 'attendance' => '*',
            'holidays' => ['view'], 'users' => ['view'],
        ]],
        'view_only' => ['View only', 'view'],
        'full' => ['Full access', '*'],
    ];

    /** Where to send someone who may not open the dashboard: route => permission, first allowed wins. */
    private const LANDING = [
        'dashboard' => 'dashboard.view', 'sales.pos' => 'pos.view', 'sales.index' => 'sales.view', 'purchases.index' => 'purchases.view',
        'products.index' => 'products.view', 'admin.employee.index' => 'employees.view', 'admin.business-reports.index' => 'reports.view',
    ];

    /** Per-request cache: user id => ['super' => bool, 'slugs' => [slug => true]]. */
    private static array $cache = [];

    /* ------------------------------------------------------------------ */
    /*  Checks                                                            */
    /* ------------------------------------------------------------------ */

    /** True when the user (default: signed-in user) has the permission — or any one of a list. */
    public static function allows($permission, ?int $userId = null): bool
    {
        $access = self::access($userId ?? (int) Auth::id());
        if ($access['super']) {
            return true;
        }
        foreach ((array) $permission as $slug) {
            if (isset($access['slugs'][$slug])) {
                return true;
            }
        }
        return false;
    }

    public static function isSuperadmin(?int $userId = null): bool
    {
        return self::access($userId ?? (int) Auth::id())['super'];
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    private static function access(int $userId): array
    {
        if (!$userId) {
            return ['super' => false, 'slugs' => []];
        }
        if (!isset(self::$cache[$userId])) {
            $role = DB::table('users as u')->join('roles as r', 'r.id', '=', 'u.role_id')
                ->where('u.id', $userId)->where('r.deleted', 'No')->where('r.status', 'Active')
                ->select('r.id', 'r.slug')->first();
            $slugs = $role ? self::roleSlugs((int) $role->id) : [];
            self::$cache[$userId] = ['super' => $role && $role->slug === self::SUPERADMIN, 'slugs' => array_fill_keys($slugs, true)];
        }
        return self::$cache[$userId];
    }

    private static function roleSlugs(int $roleId): array
    {
        return DB::table('role_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $roleId)->where('p.deleted', 'No')->where('p.status', 'Active')
            ->pluck('p.slug')->all();
    }

    /**
     * Permissions a route needs: null = not in the map (allowed, logged), [] = only a login, otherwise a list of
     * slugs of which any one is enough.
     */
    public static function requiredFor(string $routeName, ?Request $request = null): ?array
    {
        if ($routeName === '') {
            return null;
        }
        if (Str::is(self::OPEN, $routeName)) {
            return [];
        }
        foreach (self::ROUTE_MAP as $pattern => $need) {
            if (Str::is($pattern, $routeName)) {
                return self::resolve($need, $request);
            }
        }
        return null;
    }

    private static function resolve($need, ?Request $request): array
    {
        if (is_array($need)) {
            return $need;
        }
        if (str_starts_with($need, '~')) {
            $module = substr($need, 1);
            return [$module . ($request && $request->filled('id') ? '.edit' : '.create')];
        }
        if (str_starts_with($need, '@party.')) {
            // the saved party's type decides (an id is never trusted to the posted type), else the page / form type
            $action = substr($need, 7);
            $type = $request && $request->filled('id')
                ? DB::table('parties')->where('id', (int) $request->input('id'))->value('type')
                : ($request ? ($request->route('type') ?? $request->input('type')) : null);

            return [($type === 'Investor' ? 'investors.' : 'parties.') . $action];
        }
        if (str_starts_with($need, '@payment.')) {
            $action = substr($need, 9);
            $type = $request ? ($request->route('type') ?? $request->input('type')) : null;
            if (!$type && $request && $request->filled('id')) {
                $type = DB::table('payments')->where('id', (int) $request->input('id'))->value('payment_type');
            }
            return $type === 'Payment Receive' ? ['payment_receive.' . $action]
                : ($type ? ['payments.' . $action] : ['payments.' . $action, 'payment_receive.' . $action]);
        }
        if ($need === '@report') {
            return $request && $request->route('report') === 'cash-closing' ? ['cash_closing.view'] : ['reports.view'];
        }
        return [$need];
    }

    /** Log a route that is not in ROUTE_MAP / OPEN — once a day per route name. */
    public static function logUncovered(Request $request): void
    {
        $name = (string) optional($request->route())->getName();
        $key = 'perm-uncovered:' . ($name !== '' ? $name : $request->method() . ' ' . $request->path());
        try {
            if (Cache::add($key, 1, now()->addDay())) {
                Log::warning('Route without a permission rule (allowed): ' . substr($key, 16));
            }
        } catch (\Throwable $e) {
            // logging must never block the request
        }
    }

    /** "You don't have permission to add sales." */
    public static function deniedMessage(array $need): string
    {
        $verbs = ['view' => 'open', 'create' => 'add', 'edit' => 'change', 'delete' => 'delete'];
        [$module, $action] = array_pad(explode('.', (string) ($need[0] ?? ''), 2), 2, 'view');
        $label = mb_strtolower(self::MODULES[$module][0] ?? str_replace('_', ' ', $module));
        return "You don't have permission to " . ($verbs[$action] ?? $action) . ' ' . $label . '. Ask an administrator.';
    }

    /** The first page this user may open (dashboard when allowed). */
    public static function homeUrl(): string
    {
        foreach (self::LANDING as $route => $permission) {
            if (self::allows($permission)) {
                return route($route);
            }
        }
        return Auth::id() ? route('admin.users.profile', Auth::id()) : route('login');
    }

    /* ------------------------------------------------------------------ */
    /*  Catalogue, presets, lock-out guards                               */
    /* ------------------------------------------------------------------ */

    /** Every slug in MODULES => [module, action]. */
    public static function catalogue(): array
    {
        $all = [];
        foreach (self::MODULES as $module => [, , $actions]) {
            foreach ($actions as $action) {
                $all[$module . '.' . $action] = [$module, $action];
            }
        }
        return $all;
    }

    /** Insert missing permission rows for MODULES (re-activates retired ones). Returns the ids of rows added. */
    public static function syncPermissions(): array
    {
        $added = [];
        foreach (self::catalogue() as $slug => [$module, $action]) {
            $row = DB::table('permissions')->where('slug', $slug)->first();
            $values = [
                'name' => self::ACTIONS[$action] . ' ' . self::MODULES[$module][0],
                'module' => self::MODULES[$module][0],
                'description' => self::MODULES[$module][1],
                'status' => 'Active', 'deleted' => 'No', 'updated_at' => now(),
            ];
            if ($row) {
                DB::table('permissions')->where('id', $row->id)->update($values);
            } else {
                $added[] = DB::table('permissions')->insertGetId($values + ['slug' => $slug, 'created_at' => now()]);
            }
        }
        return $added;
    }

    /** Preset key => [label, [slug, …]] with '*' expanded. */
    public static function presets(): array
    {
        $out = [];
        foreach (self::PRESETS as $key => [$label, $spec]) {
            $slugs = [];
            foreach (self::catalogue() as $slug => [$module, $action]) {
                $want = is_array($spec) ? ($spec[$module] ?? null) : $spec;
                if ($want === '*' || $want === $action || (is_array($want) && in_array($action, $want, true))) {
                    $slugs[] = $slug;
                }
            }
            $out[$key] = [$label, $slugs];
        }
        return $out;
    }

    /** Only a Superadmin may give someone the Superadmin role (a user with Users access could otherwise promote anyone, themselves included). */
    public static function grantProblem(?int $newRoleId, ?int $currentRoleId = null): ?string
    {
        if ($newRoleId && $newRoleId !== $currentRoleId && self::roleIsSuper($newRoleId) && !self::isSuperadmin()) {
            return 'Only a Superadmin can give the Superadmin role.';
        }
        return null;
    }

    private static function roleIsSuper(?int $roleId): bool
    {
        return $roleId && DB::table('roles')->where('id', $roleId)->where('deleted', 'No')->where('slug', self::SUPERADMIN)->exists();
    }

    /**
     * Why this change to a user's role/status/existence is refused, or null.
     * Keeps at least one active Superadmin, and stops users moving themselves out of Roles & Permissions.
     * $newRoleId / $newStatus null = deleting the user.
     */
    public static function userChangeProblem(int $userId, ?int $newRoleId, ?string $newStatus): ?string
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            return null;
        }
        if ($problem = self::grantProblem($newRoleId, (int) $user->role_id)) {
            return $problem;
        }
        $staysSuper = $newRoleId !== null && $newStatus === 'Active' && self::roleIsSuper($newRoleId);
        if (self::roleIsSuper((int) $user->role_id) && !$staysSuper) {
            $others = DB::table('users as u')->join('roles as r', 'r.id', '=', 'u.role_id')
                ->where('u.id', '!=', $userId)->where('u.deleted', 'No')->where('u.status', 'Active')
                ->where('r.deleted', 'No')->where('r.slug', self::SUPERADMIN)->exists();
            if (!$others) {
                return 'This is the last active Superadmin. Give another active user the Superadmin role first.';
            }
        }
        if ($newRoleId !== null && $userId === (int) Auth::id() && (int) $user->role_id !== $newRoleId && !self::roleIsSuper($newRoleId)) {
            $slugs = self::roleSlugs($newRoleId);
            if (!in_array('roles.view', $slugs, true) || !in_array('roles.edit', $slugs, true)) {
                return "You can't move yourself to a role without Roles & permissions access — you would lock yourself out.";
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /*  Permissions list (custom permissions page)                        */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return view('admin.users.permissions.index');
    }

    public function list(Request $request)
    {
        $permissions = Permission::where('deleted', 'No')
            ->orderBy('module')
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        $canEdit = self::allows('roles.edit');
        $canDelete = self::allows('roles.delete');

        foreach ($permissions as $permission) {

            $status = $permission->status == 'Active'
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-danger">Inactive</span>';

            $builtIn = isset(self::catalogue()[$permission->slug]);
            $items = [];
            if ($canEdit && !$builtIn) {
                $items[] = ['label' => 'Edit', 'icon' => 'fas fa-edit', 'class' => 'editPermission', 'attrs' => ['data-id' => $permission->id]];
            }
            if ($canDelete && !$builtIn) {
                $items[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger deletePermission', 'attrs' => ['data-id' => $permission->id]];
            }

            $data[] = [
                'id' => $permission->id,
                'name' => e($permission->name),
                'slug' => e($permission->slug),
                'module' => e($permission->module ?? ''),
                'description' => e($permission->description ?? ''),
                'status' => $status,
                'actions' => $items ? action_dropdown($items) : '<span class="text-muted small">Built in</span>',
            ];
        }

        return response()->json([
            'data' => $data
        ]);
    }

    /** Slugs keep their dots (module.action). */
    private static function cleanSlug(string $slug): string
    {
        return trim(preg_replace('/[^a-z0-9._-]+/', '-', mb_strtolower(trim($slug))), '-');
    }

    public function store(Request $request)
    {
        $request->merge(['slug' => self::cleanSlug(collect([$request->slug, $request->name])->first(fn ($v) => is_string($v) && $v !== '') ?? '')]);
        $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'required|string|max:200|unique:permissions,slug',
            'module' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $permission = new Permission();

        $permission->name = $request->name;
        $permission->slug = $request->slug;
        $permission->module = $request->module;
        $permission->description = $request->description;
        $permission->status = $request->status;
        $permission->deleted = 'No';

        $permission->created_by = Auth::id();
        $permission->updated_by = Auth::id();

        $permission->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission created successfully.'
        ]);
    }

    public function edit($id)
    {
        $permission = Permission::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$permission) {
            return response()->json([
                'status' => false,
                'message' => 'Permission not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $permission
        ]);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$permission) {
            return response()->json([
                'status' => false,
                'message' => 'Permission not found.'
            ], 404);
        }
        if (isset(self::catalogue()[$permission->slug])) {
            return response()->json(['status' => false, 'message' => 'Built-in permissions cannot be changed; they are checked by the screens.'], 422);
        }

        $request->merge(['slug' => self::cleanSlug(collect([$request->slug, $request->name])->first(fn ($v) => is_string($v) && $v !== '') ?? '')]);
        $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'required|string|max:200|unique:permissions,slug,' . $id,
            'module' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
        ]);

        $permission->name = $request->name;
        $permission->slug = $request->slug;
        $permission->module = $request->module;
        $permission->description = $request->description;
        $permission->status = $request->status;
        $permission->updated_by = Auth::id();

        $permission->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission updated successfully.'
        ]);
    }

    public function destroy($id)
    {
        $permission = Permission::where('id', $id)
            ->where('deleted', 'No')
            ->first();

        if (!$permission) {
            return response()->json([
                'status' => false,
                'message' => 'Permission not found.'
            ], 404);
        }
        if (isset(self::catalogue()[$permission->slug])) {
            return response()->json(['status' => false, 'message' => 'Built-in permissions cannot be deleted; take them off a role instead.'], 422);
        }

        $permission->deleted = 'Yes';
        $permission->updated_by = Auth::id();
        $permission->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission deleted successfully.'
        ]);
    }
}
