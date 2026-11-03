<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;

/**
 * Role permissions (CheckPermission + PermissionController::ROUTE_MAP) and head-office-only screens (MainBranchOnly).
 * The seeded cashier has the Cashier preset (POS, sales view/create, sale returns, parties view/create, payment receive,
 * cash closing) and works in Branch Two.
 */
class PermissionTest extends AppTestCase
{
    private function cashierSale(): array
    {
        return [
            'sale_type' => 'Walk-In', 'sale_date' => now()->toDateString(), 'customer_phone' => '01555000111', 'customer_name' => 'Walk In',
            'discount_type' => 'Amount', 'discount_value' => 0, 'paid_amount' => 80,
            'payment_method' => $this->coa('cash'), 'payment_method_account' => $this->coa('cash-two'),
            'items' => [['product_id' => $this->ids['product.plain'], 'qty' => 1, 'sale_price' => 80, 'discount_type' => 'Amount', 'discount_value' => 0]],
        ];
    }

    public function test_cashier_can_open_pos_and_sales_list(): void
    {
        $this->signIn('cashier', 'two');

        $this->get(route('sales.pos'))->assertOk();
        $this->ajaxGet(route('sales.list'))->assertOk()->assertJson(['status' => true]);
    }

    public function test_cashier_can_store_a_sale(): void
    {
        $this->addStock($this->ids['product.plain'], 5, 'two');
        $this->signIn('cashier', 'two');

        $res = $this->ajaxPost(route('sales.store'), $this->cashierSale());

        $res->assertOk()->assertJson(['status' => true]);
        $this->assertDatabaseHas('sales', ['id' => $res->json('sale_id'), 'branch_id' => $this->branch('two'), 'grand_total' => 80]);
    }

    public function test_cashier_gets_403_json_on_purchases(): void
    {
        $this->signIn('cashier', 'two');
        $before = DB::table('purchases')->count();

        $this->ajaxGet(route('purchases.index'))->assertForbidden()->assertJson(['status' => false]);
        $res = $this->ajaxPost(route('purchases.store'), ['supplier_id' => $this->ids['supplier'], 'purchase_date' => now()->toDateString()]);
        $res->assertForbidden();
        $this->assertStringContainsString("don't have permission", (string) $res->json('message'));

        $this->assertSame($before, DB::table('purchases')->count());
    }

    public function test_cashier_gets_403_page_on_purchases(): void
    {
        $this->signIn('cashier', 'two');

        $this->get(route('purchases.index'))->assertForbidden()->assertViewIs('admin.no-permission');
    }

    public function test_cashier_gets_403_on_settings_and_settings_are_unchanged(): void
    {
        $this->signIn('cashier', 'two');

        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->ajaxPost(route('admin.settings.update'), ['company_name' => 'Hacked Co'])->assertForbidden();

        $this->assertDatabaseHas('settings', ['id' => $this->ids['setting'], 'company_name' => 'FPOS Test Co']);
    }

    public function test_cashier_gets_403_on_payroll(): void
    {
        $this->signIn('cashier', 'two');

        $this->get(route('admin.payroll-runs.index'))->assertForbidden()->assertViewIs('admin.no-permission');
        $this->ajaxGet(route('admin.payroll-runs.index'))->assertForbidden()->assertJson(['status' => false]);
        $this->ajaxPost(route('admin.payroll-runs.store'), ['month' => now()->format('Y-m'), 'run_date' => now()->toDateString()])->assertForbidden();

        $this->assertSame(0, DB::table('payroll_runs')->count());
    }

    public function test_dashboard_redirects_cashier_to_pos(): void
    {
        $this->signIn('cashier', 'two');

        $this->get(route('dashboard'))->assertRedirect(route('sales.pos'));
    }

    public function test_superadmin_passes_permission_checks(): void
    {
        $this->signIn('superadmin', 'main');

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('purchases.index'))->assertOk();
        $this->get(route('admin.settings.index'))->assertOk();
        $this->get(route('admin.payroll-runs.index'))->assertOk();
        $this->get(route('admin.accounting.trial-balance'))->assertOk();
    }

    public function test_company_wide_screens_are_refused_outside_the_main_branch(): void
    {
        $this->signIn('superadmin', 'two');

        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->get(route('admin.accounting.trial-balance'))->assertForbidden();
        $this->ajaxPost(route('admin.settings.update'), ['company_name' => 'Branch edit'])->assertForbidden();

        $this->assertDatabaseHas('settings', ['id' => $this->ids['setting'], 'company_name' => 'FPOS Test Co']);
    }

    /** Restricted role: a manager without journals.create cannot post a journal (403, nothing saved). */
    public function test_role_without_action_permission_is_refused(): void
    {
        $this->signIn('manager', 'main');
        $this->ajaxGet(route('admin.journal.list'))->assertOk();   // journals.view granted

        $this->ajaxPost(route('admin.journal.store'), ['journal_date' => now()->toDateString()])->assertForbidden();
        $this->assertSame(0, DB::table('journals')->count());
    }
}
