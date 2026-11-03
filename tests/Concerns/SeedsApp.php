<?php

namespace Tests\Concerns;

use App\Http\Controllers\Admin\AccountingReportController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\PayrollSetupController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Models\Admin\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;

/**
 * The minimum data the app needs (the schema dump has no seed rows):
 * settings, three branches (main + two), a chart of accounts with the control ledgers the accounting engine looks up
 * by slug, the permission catalogue with a Superadmin / Cashier / Branch-manager role, users, product masters,
 * products (plain, with variants, batch-tracked) and a customer and a supplier.
 *
 * Everything is inserted inside the RefreshDatabase transaction, so each test starts from the same state.
 * Ids are kept in $this->ids (e.g. $this->ids['branch.main'], $this->coa('cash-main'), $this->ids['user.cashier']).
 */
trait SeedsApp
{
    /** @var array<string,int> */
    protected array $ids = [];

    protected string $password = 'secret-pass';

    protected function seedApp(): void
    {
        $now = now();

        // ---- settings -------------------------------------------------
        $this->ids['setting'] = DB::table('settings')->insertGetId([
            'company_name' => 'FPOS Test Co', 'company_short_name' => 'FPOS', 'address' => '1 Test Road', 'phone' => '01700000000',
            'email' => 'company@example.test', 'currency' => 'BDT', 'currency_symbol' => 'Tk', 'status' => 'Active', 'deleted' => 'No',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        View::share('generalSetting', Setting::find($this->ids['setting']));

        // ---- branches -------------------------------------------------
        foreach (['main' => ['Main Branch', 'MAIN', 'Yes'], 'two' => ['Branch Two', 'B2', 'No'], 'three' => ['Branch Three', 'B3', 'No']] as $k => [$name, $code, $main]) {
            $this->ids['branch.' . $k] = DB::table('branches')->insertGetId([
                'name' => $name, 'code' => $code, 'is_main' => $main, 'status' => 'Active', 'deleted' => 'No',
                'created_date' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->seedChartOfAccounts();
        $this->seedRolesAndUsers();
        $this->seedProducts();
        $this->seedParties();
    }

    /* ------------------------------------------------------------------ */

    protected function seedChartOfAccounts(): void
    {
        $roots = [
            'assets'       => ['10000000', 'Assets', 'Asset', 'Debit'],
            'liabilities'  => ['20000000', 'Liabilities', 'Liability', 'Credit'],
            'equity'       => ['30000000', 'Equity', 'Equity', 'Credit'],
            'income'       => ['40000000', 'Income', 'Income', 'Credit'],
            'expense'      => ['50000000', 'Expense', 'Expense', 'Debit'],
            'liquid-asset' => ['60000000', 'Liquid Asset', 'Liquid Asset', 'Debit'],
        ];
        $groups = [
            // slug => [root, code, name]
            'current-asset'       => ['assets', '10100000', 'Current Asset'],
            'advance-prepayments' => ['assets', '10200000', 'Advance & Prepayments'],
            'current-liability'   => ['liabilities', '20100000', 'Current Liability'],
            'owner-s-equity'      => ['equity', '30100000', "Owner's Equity"],
            'reserves-surplus'    => ['equity', '30200000', 'Reserves & Surplus'],
            'sales-income'        => ['income', '40100000', 'Sales Income'],
            'other-income'        => ['income', '40200000', 'Other Income'],
            'cogs'                => ['expense', '50100000', 'COGS'],
            'operating-expenses'  => ['expense', '50200000', 'Operating Expenses'],
            'selling-marketing'   => ['expense', '50300000', 'Selling & Marketing'],
            'cash'                => ['liquid-asset', '60100000', 'Cash'],
            'bank'                => ['liquid-asset', '60200000', 'Bank'],
        ];
        $ledgers = [
            // slug => [group, code, name, branch key|null]
            'accounts-receivable'           => ['current-asset', '10101000', 'Accounts Receivable', null],
            'inventory'                     => ['current-asset', '10102000', 'Inventory', null],
            'vat-current-account-input-vat' => ['current-asset', '10103000', 'VAT Current Account (Input VAT)', null],
            'employee-loans'                => ['current-asset', '10104000', 'Employee Loans', null],
            'accounts-payable'              => ['current-liability', '20101000', 'Accounts Payable', null],
            'vat-payable-output-vat'        => ['current-liability', '20102000', 'VAT Payable (Output VAT)', null],
            'customer-advances'             => ['current-liability', '20103000', 'Customer Advances', null],
            'salary-payable'                => ['current-liability', '20104000', 'Salary Payable', null],
            'pf-payable'                    => ['current-liability', '20105000', 'PF Payable', null],
            'owner-capital'                 => ['owner-s-equity', '30101000', 'Owner Capital', null],
            'retained-earnings'             => ['reserves-surplus', '30201000', 'Retained Earnings', null],
            'sales-revenue'                 => ['sales-income', '40101000', 'Sales Revenue', null],
            'sales-return-allowances'       => ['sales-income', '40102000', 'Sales Return & Allowances', null],
            'discount-received'             => ['other-income', '40201000', 'Discount Received', null],
            'rent-expense'                  => ['operating-expenses', '50201000', 'Rent Expense', null],
            'utilities-expense'             => ['operating-expenses', '50202000', 'Utilities Expense', null],
            'salary-expense'                => ['operating-expenses', '50203000', 'Salary Expense', null],
            'cash-main'                     => ['cash', '60101000', 'Cash Main', 'main'],
            'cash-two'                      => ['cash', '60102000', 'Cash Branch Two', 'two'],
            'cash-three'                    => ['cash', '60103000', 'Cash Branch Three', 'three'],
            'bank-account'                  => ['bank', '60201000', 'City Bank A/C', null],
        ];

        $row = fn (array $v) => $v + ['opening_balance' => 0, 'current_balance' => 0, 'is_system' => 0, 'deleted' => 'No', 'status' => 'Active',
            'created_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()];

        $types = [];
        foreach ($roots as $slug => [$code, $name, $type, $side]) {
            $types[$slug] = [$type, $side];
            $this->ids['coa.' . $slug] = DB::table('chart_of_accounts')->insertGetId($row([
                'account_code' => $code, 'account_name' => $name, 'slug' => $slug, 'layer_level' => 1, 'parent_id' => null,
                'account_type' => $type, 'balance' => $side, 'is_system' => 1,
            ]));
        }
        foreach ($groups as $slug => [$root, $code, $name]) {
            [$type, $side] = $types[$root];
            $types[$slug] = [$type, $side];
            $this->ids['coa.' . $slug] = DB::table('chart_of_accounts')->insertGetId($row([
                'account_code' => $code, 'account_name' => $name, 'slug' => $slug, 'layer_level' => 2, 'parent_id' => $this->ids['coa.' . $root],
                'account_type' => $type, 'balance' => $side,
            ]));
        }
        foreach ($ledgers as $slug => [$group, $code, $name, $branch]) {
            [$type, $side] = $types[$group];
            $this->ids['coa.' . $slug] = DB::table('chart_of_accounts')->insertGetId($row([
                'account_code' => $code, 'account_name' => $name, 'slug' => $slug, 'layer_level' => 3, 'parent_id' => $this->ids['coa.' . $group],
                'branch_id' => $branch ? $this->ids['branch.' . $branch] : null, 'account_type' => $type, 'balance' => $side,
            ]));
        }
    }

    protected function seedRolesAndUsers(): void
    {
        PermissionController::syncPermissions();
        $presets = PermissionController::presets();
        $permIds = DB::table('permissions')->pluck('id', 'slug');

        $role = function (string $slug, string $name, array $slugs) use ($permIds) {
            $id = DB::table('roles')->insertGetId(['name' => $name, 'slug' => $slug, 'status' => 'Active', 'deleted' => 'No']);
            $rows = [];
            foreach ($slugs as $s) {
                if (isset($permIds[$s])) {
                    $rows[] = ['role_id' => $id, 'permission_id' => $permIds[$s], 'created_at' => now(), 'updated_at' => now()];
                }
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('role_permissions')->insert($chunk);
            }

            return $id;
        };

        $this->ids['role.superadmin'] = $role(PermissionController::SUPERADMIN, 'Superadmin', []);
        $this->ids['role.cashier'] = $role('cashier', 'Cashier', $presets['cashier'][1]);
        $this->ids['role.manager'] = $role('branch-manager', 'Branch manager', $presets['branch_manager'][1]);
        $this->ids['role.accountant'] = $role('accountant', 'Accountant', $presets['accountant'][1]);
        $this->ids['role.hr'] = $role('hr', 'HR & payroll', $presets['hr'][1]);

        // superadmin: every branch. cashier: Branch Two only. manager: Main + Branch Two (not Three).
        $this->ids['user.superadmin'] = $this->makeUser('superadmin@example.test', 'Super Admin', 'superadmin', 'main', [], 'Yes');
        $this->ids['user.cashier'] = $this->makeUser('cashier@example.test', 'Cashier Two', 'cashier', 'two', ['two']);
        $this->ids['user.manager'] = $this->makeUser('manager@example.test', 'Manager Multi', 'manager', 'main', ['main', 'two']);
        $this->ids['user.cashier_main'] = $this->makeUser('cashier.main@example.test', 'Cashier Main', 'cashier', 'main', ['main']);
        $this->ids['user.accountant'] = $this->makeUser('accountant@example.test', 'Accountant', 'accountant', 'main', ['main']);
        $this->ids['user.hr'] = $this->makeUser('hr@example.test', 'HR Officer', 'hr', 'main', ['main']);
        $this->ids['user.inactive'] = $this->makeUser('inactive@example.test', 'Inactive User', 'superadmin', 'main', ['main'], 'No', ['status' => 'Inactive']);
        $this->ids['user.deleted'] = $this->makeUser('deleted@example.test', 'Deleted User', 'superadmin', 'main', ['main'], 'No', ['deleted' => 'Yes']);
    }

    protected function makeUser(string $email, string $name, string $role, string $home, array $branches, string $all = 'No', array $extra = []): int
    {
        static $hash = null;
        $hash ??= Hash::make($this->password);

        $id = DB::table('users')->insertGetId($extra + [
            'name' => $name, 'email' => $email, 'password' => $hash, 'email_verified_at' => now(),
            'role_id' => $this->ids['role.' . $role], 'branch_id' => $this->ids['branch.' . $home], 'all_branches' => $all,
            'status' => 'Active', 'deleted' => 'No', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (array_unique(array_merge($branches, $all === 'Yes' ? [] : [$home])) as $b) {
            DB::table('branch_user')->insert(['user_id' => $id, 'branch_id' => $this->ids['branch.' . $b]]);
        }

        return $id;
    }

    protected function seedProducts(): void
    {
        $now = now();
        $this->ids['unit'] = DB::table('units')->insertGetId(['name' => 'Piece', 'slug' => 'piece', 'status' => 'Active', 'deleted' => 'No', 'created_at' => $now, 'updated_at' => $now]);
        $this->ids['category'] = DB::table('categories')->insertGetId(['name' => 'General', 'slug' => 'general', 'status' => 'Active', 'deleted' => 'No', 'created_at' => $now, 'updated_at' => $now]);
        $this->ids['brand'] = DB::table('brands')->insertGetId(['name' => 'Acme', 'slug' => 'acme', 'status' => 'Active', 'deleted' => 'No', 'created_at' => $now, 'updated_at' => $now]);
        $this->ids['attr_type'] = DB::table('attribute_types')->insertGetId(['name' => 'Color', 'slug' => 'color', 'status' => 'Active', 'deleted' => 'No', 'created_at' => $now, 'updated_at' => $now]);
        $this->ids['attr.red'] = DB::table('attributes')->insertGetId(['name' => 'Red', 'slug' => 'red', 'attribute_type_id' => $this->ids['attr_type'], 'status' => 'Active', 'deleted' => 'No', 'created_at' => $now, 'updated_at' => $now]);
        $this->ids['attr.blue'] = DB::table('attributes')->insertGetId(['name' => 'Blue', 'slug' => 'blue', 'attribute_type_id' => $this->ids['attr_type'], 'status' => 'Active', 'deleted' => 'No', 'created_at' => $now, 'updated_at' => $now]);

        $this->ids['product.plain'] = $this->makeProduct('Plain Rice', 'RICE-1', 50, 80);
        $this->ids['product.variant'] = $this->makeProduct('T-Shirt', 'TS-1', 200, 350);
        $this->ids['product.batch'] = $this->makeProduct('Paracetamol', 'PARA-1', 2, 5, ['track_batch' => 'Yes']);
        foreach (['red', 'blue'] as $c) {
            $this->ids['variant.' . $c] = DB::table('product_variants')->insertGetId([
                'product_id' => $this->ids['product.variant'], 'attribute_type_id' => $this->ids['attr_type'], 'attribute_id' => $this->ids['attr.' . $c],
                'barcode' => 'TS-' . strtoupper($c), 'sale_price' => 350, 'status' => 'Active', 'deleted' => 'No', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    protected function makeProduct(string $name, string $sku, float $cost, float $price, array $extra = []): int
    {
        return DB::table('products')->insertGetId($extra + [
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . strtolower($sku), 'sku' => $sku, 'barcode' => $sku,
            'unit_id' => $this->ids['unit'], 'category_id' => $this->ids['category'], 'brand_id' => $this->ids['brand'],
            'purchase_price' => $cost, 'sale_price' => $price, 'status' => 'Active', 'deleted' => 'No',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function seedParties(): void
    {
        $this->ids['customer'] = $this->makeParty('Customer', 'Rahim Traders', '01811111111');
        $this->ids['supplier'] = $this->makeParty('Supplier', 'Karim Supplies', '01922222222');
    }

    protected function makeParty(string $type, string $name, string $phone, array $extra = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('parties')->insertGetId($extra + [
            'code' => ($type === 'Supplier' ? 'SUP-' : 'CUS-') . str_pad((string) $n, 5, '0', STR_PAD_LEFT) . random_int(100, 999),
            'type' => $type, 'name' => $name, 'phone' => $phone, 'address' => 'Dhaka', 'opening_balance' => 0,
            'opening_balance_type' => 'Due', 'status' => 'Active', 'deleted' => 'No', 'created_date' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    protected function coa(string $slug): int
    {
        return $this->ids['coa.' . $slug];
    }

    protected function branch(string $key): int
    {
        return $this->ids['branch.' . $key];
    }

    protected function user(string $key): User
    {
        return User::findOrFail($this->ids['user.' . $key]);
    }

    /** Opening stock row in a branch (explicit branch, like StockController::move()). */
    protected function addStock(int $productId, float $qty, string $branch = 'main', ?int $variantId = null, ?int $batchId = null, ?string $date = null): void
    {
        StockController::move([
            'branch_id' => $this->branch($branch), 'product_id' => $productId, 'product_variant_id' => $variantId, 'batch_id' => $batchId,
            'qty' => $qty, 'reference_type' => 'Opening Stock', 'date' => $date ?? now()->subDays(30)->toDateTimeString(), 'remarks' => 'Test opening stock',
        ]);
    }

    /**
     * Cash in a drawer before a test's documents: the cash ledger's opening balance, which the Cash & Bank Book and the
     * statements count. Money out of a Cash ledger beyond what it holds is refused, so tests that pay cash fund it first.
     */
    protected function fundCash(float $amount, string $slug = 'cash-main'): void
    {
        DB::table('chart_of_accounts')->where('id', $this->coa($slug))->update(['opening_balance' => $amount]);
    }

    protected function makeBatch(int $productId, string $batchNo, ?string $expiry, float $cost = 2): int
    {
        return StockController::batchFor($productId, null, $batchNo, $expiry, null, $cost, null);
    }

    protected function setSettings(array $values): void
    {
        SystemSettingController::put($values);
        self::resetAppStatics();
    }

    /**
     * Sign in the way the login form does: auth user + session loggedInBranchId, and the @fpos_branch
     * MySQL variable SetBranch sets (for fixtures inserted between requests, and for unit-level calls).
     */
    protected function signIn(string|User $user = 'superadmin', string $branch = 'main'): static
    {
        $user = is_string($user) ? $this->user($user) : $user;
        $branchId = $this->branch($branch);
        self::resetAppStatics();
        $this->actingAs($user);
        $this->withSession([BranchController::SESSION_KEY => $branchId]);
        session([BranchController::SESSION_KEY => $branchId]);
        Auth::setUser($user);
        DB::statement('SET @fpos_branch = ?', [$branchId]);

        return $this;
    }

    /**
     * The app keeps per-request caches in static properties (fine under php-fpm: one process per request).
     * PHPUnit runs every request in one process, so clear them before each request.
     */
    public static function resetAppStatics(): void
    {
        static $props = null;
        if ($props === null) {
            $props = [];
            foreach (glob(base_path('app/Http/{Controllers/Admin,Middleware}/*.php'), GLOB_BRACE) as $file) {
                $class = (str_contains($file, 'Middleware') ? 'App\\Http\\Middleware\\' : 'App\\Http\\Controllers\\Admin\\') . basename($file, '.php');
                if (!class_exists($class)) {
                    continue;
                }
                foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_STATIC) as $r) {
                    if ($r->getDeclaringClass()->getName() === $class && $r->hasDefaultValue()) {
                        $r->setAccessible(true);
                        $props[] = $r;
                    }
                }
            }
        }
        foreach ($props as $r) {
            $r->setValue(null, $r->getDefaultValue());
        }
    }
}
