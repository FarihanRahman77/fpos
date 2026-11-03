<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\BranchHelpers;

/**
 * Branch visibility rule:
 * - a user permitted in ONE branch sees only that branch's data;
 * - a user permitted in SEVERAL branches sees those branches' data combined in lists / reports, never a branch they are
 *   not permitted in (the seeded manager: Main + Two, not Three) — whichever of them they signed in to;
 * - records are changed (edited / deleted / cancelled / returned) only in the branch the user signed in to;
 *   another branch's record by id → 403 (visible branch) or 404 (not visible), and nothing changes.
 *
 * Fixtures: in each branch one sale (with a customer credit row), one purchase, one receipt, one expense, one employee,
 * each numbered with the branch name (INV-MAIN-…, INV-TWO-…, INV-THREE-…).
 * Customer dues: Main 100 − 10 = 90, Two 200 − 20 = 180, Three 400 − 40 = 360.
 */
class BranchIsolationTest extends AppTestCase
{
    use BranchHelpers;

    private array $doc = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['main' => [100, 10], 'two' => [200, 20], 'three' => [400, 40]] as $b => [$sale, $paid]) {
            $U = strtoupper($b);
            $this->doc["sale.$b"] = $this->sale($b, "INV-$U-0001", $sale);
            $this->doc["purchase.$b"] = $this->purchase($b, "PINV-$U-0001", 1000);
            $this->doc["receipt.$b"] = $this->receipt($b, "RCV-$U-0001", $paid);
            $this->doc["expense.$b"] = $this->expense($b, "EXP-$U-0001", 55);
            $this->doc["employee.$b"] = $this->employee($b, "EMP-$U-01", ucfirst($b) . ' Employee');
        }
        // a single-branch user with the branch-manager permissions, permitted in Branch Two only
        $this->ids['user.manager_two'] = $this->makeUser('manager.two@example.test', 'Manager Two Only', 'manager', 'two', ['two']);
    }

    /* ------------------------------------------------------------------ */
    /*  helpers                                                          */
    /* ------------------------------------------------------------------ */

    /** Every list / report endpoint covered, with the document number each one shows. */
    private function listings(): array
    {
        $from = now()->subDays(5)->toDateString();
        $to = now()->addDay()->toDateString();

        return [
            'sales'     => ['/admin/sales/list', 'INV-%s-0001'],
            'purchases' => ['/admin/purchases/list', 'PINV-%s-0001'],
            'receipts'  => ['/admin/payment/list/Payment Receive', 'RCV-%s-0001'],
            'expenses'  => ["/admin/expense/list?from=$from&to=$to", 'EXP-%s-0001'],
            'employees' => ['/admin/employee/list', 'EMP-%s-01'],
            'report'    => ["/admin/business-reports/sales/data?group_by=invoice&from=$from&to=$to", 'INV-%s-0001'],
        ];
    }

    /** Asserts every listing shows exactly the documents of $visible and none of the other branches. */
    private function assertListsShow(array $visible): void
    {
        foreach ($this->listings() as $name => [$uri, $pattern]) {
            $res = $this->ajaxGet($uri);
            $res->assertOk();
            $body = (string) $res->getContent();
            foreach (['main', 'two', 'three'] as $b) {
                $needle = sprintf($pattern, strtoupper($b));
                if (in_array($b, $visible, true)) {
                    $this->assertStringContainsString($needle, $body, "$name list should show $needle");
                } else {
                    $this->assertStringNotContainsString($needle, $body, "$name list must not show $needle");
                }
            }
        }
    }

    private function customerDue(): float
    {
        $res = $this->ajaxGet('/admin/sales/customer-due/' . $this->ids['customer'])->assertOk();

        return (float) $res->json('due');
    }

    private function assertRefused($response): void
    {
        $this->assertContains($response->getStatusCode(), [403, 404], 'Expected 403/404, got ' . $response->getStatusCode()
            . ': ' . substr((string) $response->getContent(), 0, 300));
    }

    /** Refused, also accepting the purchase delete's 422 "Purchase not found." (status covered by its own test). */
    private function assertRefusedOrNotFound422($response): void
    {
        if ($response->getStatusCode() === 422) {
            $this->assertStringContainsString('not found', (string) $response->json('message'));

            return;
        }
        $this->assertRefused($response);
    }

    private function assertUntouched(string $table, int $id): void
    {
        $this->assertDatabaseHas($table, ['id' => $id, 'deleted' => 'No']);
    }

    /* ------------------------------------------------------------------ */
    /*  single-branch users                                               */
    /* ------------------------------------------------------------------ */

    public function test_single_branch_user_sees_only_own_branch_in_every_list_and_report(): void
    {
        $this->signIn('manager_two', 'two');

        $this->assertListsShow(['two']);
        $this->assertSame(180.0, $this->customerDue());
    }

    public function test_cashier_sees_only_branch_two_sales_receipts_and_dues(): void
    {
        $this->signIn('cashier', 'two');

        $sales = (string) $this->ajaxGet('/admin/sales/list')->assertOk()->getContent();
        $this->assertStringContainsString('INV-TWO-0001', $sales);
        $this->assertStringNotContainsString('INV-MAIN-0001', $sales);
        $this->assertStringNotContainsString('INV-THREE-0001', $sales);

        $receipts = (string) $this->ajaxGet('/admin/payment/list/Payment Receive')->assertOk()->getContent();
        $this->assertStringContainsString('RCV-TWO-0001', $receipts);
        $this->assertStringNotContainsString('RCV-MAIN-0001', $receipts);
        $this->assertStringNotContainsString('RCV-THREE-0001', $receipts);

        $this->assertSame(180.0, $this->customerDue());
    }

    public function test_single_branch_user_cannot_open_another_branch_record_by_id(): void
    {
        $this->signIn('cashier', 'two');

        $this->assertRefused($this->get('/admin/sales/invoice/' . $this->doc['sale.main'] . '/pdf'));
        $this->assertRefused($this->get('/admin/sales/invoice/' . $this->doc['sale.three'] . '/pdf'));
        $this->assertRefused($this->get('/admin/sales/return/' . $this->doc['sale.main']));

        $this->signIn('manager_two', 'two');
        $this->assertRefused($this->ajaxGet('/admin/expense/show?id=' . $this->doc['expense.main']));
        $this->assertRefused($this->ajaxGet('/admin/employee/show?id=' . $this->doc['employee.main']));
        $this->assertRefused($this->get('/admin/purchases/edit/' . $this->doc['purchase.main']));
    }

    public function test_single_branch_user_cannot_delete_another_branch_records(): void
    {
        $this->signIn('manager_two', 'two');

        $this->assertRefused($this->ajaxPost('/admin/sales/delete/' . $this->doc['sale.main']));
        $this->assertRefusedOrNotFound422($this->ajaxPost('/admin/purchases/delete/' . $this->doc['purchase.main'])); // see the 404 test below
        $this->assertRefused($this->ajaxPost('/admin/payment/delete', ['id' => $this->doc['receipt.main']]));
        $this->assertRefused($this->ajaxPost('/admin/expense/delete', ['id' => $this->doc['expense.main']]));
        $this->assertRefused($this->ajaxPost('/admin/employee/delete', ['id' => $this->doc['employee.main']]));

        $this->assertUntouched('sales', $this->doc['sale.main']);
        $this->assertUntouched('purchases', $this->doc['purchase.main']);
        $this->assertUntouched('payments', $this->doc['receipt.main']);
        $this->assertUntouched('expenses', $this->doc['expense.main']);
        $this->assertUntouched('employees', $this->doc['employee.main']);
        $this->assertSame(0, DB::table('stocks')->where('sale_id', $this->doc['sale.main'])->where('deleted', 'Yes')->count());
    }

    /** Control: the same user CAN delete a record of the branch they signed in to (so the refusals above are about the branch). */
    public function test_user_can_delete_own_branch_expense(): void
    {
        $this->signIn('manager_two', 'two');

        $this->ajaxPost('/admin/expense/delete', ['id' => $this->doc['expense.two']])->assertOk();
        $this->assertDatabaseHas('expenses', ['id' => $this->doc['expense.two'], 'deleted' => 'Yes']);
    }

    /* ------------------------------------------------------------------ */
    /*  multi-branch users (Main + Two, not Three)                       */
    /* ------------------------------------------------------------------ */

    public function test_multi_branch_lists_signed_in_to_main_are_combined(): void
    {
        $this->signIn('manager', 'main');

        $this->assertListsShow(['main', 'two']);
    }

    public function test_multi_branch_lists_signed_in_to_two_are_combined(): void
    {
        $this->signIn('manager', 'two');

        $this->assertListsShow(['main', 'two']);
    }

    public function test_multi_branch_customer_due_is_combined(): void
    {
        $this->signIn('manager', 'main');
        $this->assertSame(270.0, $this->customerDue()); // 90 + 180, never Three's 360

        $this->signIn('manager', 'two');
        $this->assertSame(270.0, $this->customerDue());
    }

    public function test_multi_branch_user_can_open_records_of_permitted_branches_combined(): void
    {
        $this->signIn('manager', 'main');

        $this->get('/admin/sales/invoice/' . $this->doc['sale.two'] . '/pdf')->assertOk();
        $this->ajaxGet('/admin/expense/show?id=' . $this->doc['expense.two'])->assertOk();
        $this->ajaxGet('/admin/employee/show?id=' . $this->doc['employee.two'])->assertOk();
    }

    /** Branch Three is never visible to the manager, whatever branch they signed in to. */
    public function test_multi_branch_user_cannot_open_non_permitted_branch_records(): void
    {
        $this->signIn('manager', 'main');

        $this->assertRefused($this->get('/admin/sales/invoice/' . $this->doc['sale.three'] . '/pdf'));
        $this->assertRefused($this->get('/admin/sales/return/' . $this->doc['sale.three']));
        $this->assertRefused($this->get('/admin/purchases/edit/' . $this->doc['purchase.three']));
        $this->assertRefused($this->ajaxGet('/admin/expense/show?id=' . $this->doc['expense.three']));
        $this->assertRefused($this->ajaxGet('/admin/employee/show?id=' . $this->doc['employee.three']));
    }

    /** Changes only in the signed-in branch: Main login cannot delete / return / edit Branch Two (visible) or Three records. */
    public function test_multi_branch_user_cannot_change_records_of_another_branch(): void
    {
        $this->signIn('manager', 'main');

        foreach (['two', 'three'] as $b) {
            $this->assertRefused($this->ajaxPost('/admin/sales/delete/' . $this->doc["sale.$b"]));
            $this->assertRefusedOrNotFound422($this->ajaxPost('/admin/purchases/delete/' . $this->doc["purchase.$b"]));
            $this->assertRefused($this->ajaxPost('/admin/payment/delete', ['id' => $this->doc["receipt.$b"]]));
            $this->assertRefused($this->ajaxPost('/admin/expense/delete', ['id' => $this->doc["expense.$b"]]));
            $this->assertRefused($this->ajaxPost('/admin/employee/delete', ['id' => $this->doc["employee.$b"]]));
            $this->assertRefused($this->get('/admin/sales/return/' . $this->doc["sale.$b"]));
            $this->assertRefused($this->get('/admin/purchases/edit/' . $this->doc["purchase.$b"]));

            $this->assertUntouched('sales', $this->doc["sale.$b"]);
            $this->assertUntouched('purchases', $this->doc["purchase.$b"]);
            $this->assertUntouched('payments', $this->doc["receipt.$b"]);
            $this->assertUntouched('expenses', $this->doc["expense.$b"]);
            $this->assertUntouched('employees', $this->doc["employee.$b"]);
        }
        $this->assertSame(0, DB::table('payments')->where('deleted', 'Yes')->count());
    }

    /** A visible-but-not-own record is refused with 403 and a "sign in to <branch>" message (not a silent 404). */
    public function test_multi_branch_change_of_visible_branch_record_says_sign_in_there(): void
    {
        $this->signIn('manager', 'main');

        $res = $this->ajaxPost('/admin/expense/delete', ['id' => $this->doc['expense.two']]);
        $res->assertStatus(403);
        $this->assertStringContainsString('Sign in to Branch Two', (string) $res->json('message'));
        $this->assertUntouched('expenses', $this->doc['expense.two']);
    }

    /** A purchase id from a branch the user cannot see is "not found": 404, like the other documents (not 422). */
    public function test_deleting_purchase_of_non_visible_branch_answers_404(): void
    {
        $this->signIn('manager_two', 'two');

        $this->ajaxPost('/admin/purchases/delete/' . $this->doc['purchase.main'])->assertNotFound();
        $this->assertUntouched('purchases', $this->doc['purchase.main']);
    }
}
