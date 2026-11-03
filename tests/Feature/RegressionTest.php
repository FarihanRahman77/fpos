<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\AppTestCase;
use Tests\Concerns\BranchHelpers;

/**
 * Regression tests for defects fixed in this project (one named test each). Other regressions live next to their
 * workflow (sale return SQL ambiguity, purchase return sign, PF ledger column, employee delete with a running loan,
 * cash closing re-flow) in the sales / purchase / payroll / accounting test classes.
 */
class RegressionTest extends AppTestCase
{
    use BranchHelpers;

    /** A cash ledger with entries cannot be moved to another branch (it would move its history); an unused one can. */
    public function test_regression_coa_ledger_in_use_cannot_change_branch(): void
    {
        $this->receipt('two', 'RCV-TWO-REG', 50);   // uses cash-two as payment_method_account_id
        $this->signIn('superadmin', 'main');

        $res = $this->ajaxPost(route('admin.chart-of-accounts.update', $this->coa('cash-two')), ['branch_id' => $this->branch('main')]);

        $res->assertStatus(422);
        $this->assertStringContainsString('already used', json_encode($res->json()));
        $this->assertNoSqlLeak($res);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $this->coa('cash-two'), 'branch_id' => $this->branch('two')]);

        // control: the unused Branch Three ledger may move
        $this->ajaxPost(route('admin.chart-of-accounts.update', $this->coa('cash-three')), ['branch_id' => $this->branch('two')])->assertOk();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $this->coa('cash-three'), 'branch_id' => $this->branch('two')]);
    }

    /**
     * List / search endpoints must never return a product name as raw HTML. These endpoints return the name as a JSON
     * data field (the screens escape it with escapeHtml() when rendering); any HTML fragment they return, and the sales
     * list rows (server-built HTML), must carry it escaped.
     */
    public function test_regression_product_name_with_quotes_is_escaped_in_list_endpoints(): void
    {
        $name = 'Tom\'s "Best" <b>Rice</b>';
        $id = $this->makeProduct($name, 'QUOTE-1', 10, 20);
        $this->addStock($id, 5, 'main');
        $this->sale('main', 'INV-QUOTE-1', 20);
        DB::table('sale_items')->where('sale_id', DB::table('sales')->where('invoice_no', 'INV-QUOTE-1')->value('id'))->update(['product_id' => $id]);
        $this->signIn('superadmin', 'main');

        $endpoints = [
            route('products.list'),
            route('sales.products.search', ['search' => 'Tom']),
            route('purchases.products.search', ['search' => 'Tom']),
            route('admin.stock.products'),
        ];
        foreach ($endpoints as $uri) {
            $res = $this->ajaxGet($uri);
            $res->assertOk();
            $this->assertStringContainsString('Tom', (string) $res->getContent(), "$uri should list the product");
            foreach ($this->htmlFragments($res->json()) as $fragment) {
                $this->assertStringNotContainsString('<b>Rice</b>', $fragment, "$uri returns the product name as raw HTML");
                $this->assertStringNotContainsString('"Best"', $fragment, "$uri returns unescaped quotes inside HTML");
            }
        }
        // the sales list builds its rows as HTML too
        $sales = (string) $this->ajaxGet(route('sales.list'))->assertOk()->json('html');
        $this->assertStringContainsString('INV-QUOTE-1', $sales);
        $this->assertStringNotContainsString('<b>Rice</b>', $sales);
    }

    /** The ledger <option> list (admin.getCoaChilds) escapes account names. */
    public function test_regression_getcoachilds_escapes_names(): void
    {
        DB::table('chart_of_accounts')->insert([
            'account_code' => '50204000', 'account_name' => '<script>alert(1)</script>', 'slug' => 'xss', 'layer_level' => 3,
            'parent_id' => $this->coa('operating-expenses'), 'account_type' => 'Expense', 'balance' => 'Debit',
            'deleted' => 'No', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->signIn('superadmin', 'main');

        $res = $this->get(route('admin.getCoaChilds', ['parent_id' => $this->coa('operating-expenses')]));

        $res->assertOk();
        $body = (string) $res->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringContainsString('Rent Expense', $body);
    }

    public function test_regression_deleted_user_cannot_log_in(): void
    {
        $this->post('/login', ['email' => 'deleted@example.test', 'password' => $this->password, 'branch_id' => $this->branch('main')])
            ->assertSessionHasErrors('branch_id');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $this->ids['user.deleted'], 'deleted' => 'Yes']);
    }

    public function test_regression_register_route_removed(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.test']);
    }

    /** Every string in a decoded JSON payload that looks like an HTML fragment. */
    private function htmlFragments($data): array
    {
        $out = [];
        array_walk_recursive($data, function ($v) use (&$out) {
            if (is_string($v) && preg_match('/<(td|tr|span|div|option|a|strong|i|small|button|img|li)[\s>]/i', $v)) {
                $out[] = $v;
            }
        });

        return $out;
    }
}
