<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;

/**
 * CSRF protection on state-changing requests, and validation answers: 422 with a human message, never SQL text.
 */
class SecurityTest extends AppTestCase
{
    /** Laravel skips CSRF checks while running unit tests; this binds a VerifyCsrfToken that does check. */
    private function enforceCsrf(): void
    {
        $this->app->instance(VerifyCsrfToken::class, new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    private function partyData(array $over = []): array
    {
        return $over + [
            'type' => 'Customer', 'name' => 'Csrf Customer', 'phone' => '01712345678', 'address' => 'Dhaka',
            'opening_balance' => 0, 'opening_balance_type' => 'Due', 'status' => 'Active',
        ];
    }

    public function test_post_without_csrf_token_is_rejected_with_419(): void
    {
        $this->enforceCsrf();
        $this->signIn('superadmin', 'main');

        $this->post(route('party.store'), $this->partyData())->assertStatus(419);

        $this->assertDatabaseMissing('parties', ['name' => 'Csrf Customer']);
    }

    public function test_post_with_valid_csrf_token_is_accepted(): void
    {
        $this->enforceCsrf();
        $this->signIn('superadmin', 'main');
        $this->withSession(['_token' => 'test-csrf-token']);

        $this->ajaxPost(route('party.store'), $this->partyData(['_token' => 'test-csrf-token']))->assertOk();

        $this->assertDatabaseHas('parties', ['name' => 'Csrf Customer', 'type' => 'Customer']);
    }

    public function test_logout_without_csrf_token_is_rejected(): void
    {
        $this->enforceCsrf();
        $this->signIn('cashier', 'two');

        $this->post('/logout')->assertStatus(419);
        $this->assertAuthenticated();
    }

    /* ------------------------------------------------------------------ */
    /*  Validation                                                        */
    /* ------------------------------------------------------------------ */

    private function saleData(array $over = []): array
    {
        return $over + [
            'sale_type' => 'Wholesale', 'sale_date' => now()->toDateString(), 'customer_id' => $this->ids['customer'],
            'discount_type' => 'Amount', 'discount_value' => 0, 'paid_amount' => 0,
            'items' => [['product_id' => $this->ids['product.plain'], 'qty' => 1, 'sale_price' => 80, 'discount_type' => 'Amount', 'discount_value' => 0]],
        ];
    }

    private function assertHuman422($response, ?string $contains = null): void
    {
        $response->assertStatus(422);
        $message = (string) ($response->json('message') ?? '');
        $this->assertNotSame('', $message, 'A 422 must carry a message');
        if ($contains !== null) {
            $this->assertStringContainsStringIgnoringCase($contains, $message . ' ' . json_encode($response->json('errors')));
        }
        $this->assertNoSqlLeak($response);
    }

    public function test_sale_validation_missing_negative_and_too_long_input(): void
    {
        $this->addStock($this->ids['product.plain'], 10, 'main');
        $this->signIn('superadmin', 'main');

        $this->assertHuman422($this->ajaxPost(route('sales.store'), $this->saleData(['items' => []])), 'Add at least one product');
        $this->assertHuman422($this->ajaxPost(route('sales.store'), $this->saleData(['customer_id' => null])), 'Select the customer');
        $this->assertHuman422($this->ajaxPost(route('sales.store'), $this->saleData(['paid_amount' => -5])), 'paid amount');
        $this->assertHuman422($this->ajaxPost(route('sales.store'), $this->saleData([
            'items' => [['product_id' => $this->ids['product.plain'], 'qty' => -1, 'sale_price' => 80, 'discount_type' => 'Amount']],
        ])), 'Quantity must be more than zero');
        $this->assertHuman422($this->ajaxPost(route('sales.store'), $this->saleData([
            'sale_type' => 'Walk-In', 'customer_id' => null, 'customer_phone' => str_repeat('9', 40),
        ])), 'customer phone');
        $this->assertHuman422($this->ajaxPost(route('sales.store'), $this->saleData(['items' => '{not json'])), 'Invalid sale items');

        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(0, DB::table('stocks')->where('reference_type', 'Sale')->count());
    }

    public function test_party_validation_missing_negative_and_too_long_input(): void
    {
        $this->signIn('superadmin', 'main');
        $before = DB::table('parties')->count();

        $this->assertHuman422($this->ajaxPost(route('party.store'), $this->partyData(['name' => ''])), 'name');
        $this->assertHuman422($this->ajaxPost(route('party.store'), $this->partyData(['opening_balance' => -100])), 'positive amount');
        $this->assertHuman422($this->ajaxPost(route('party.store'), $this->partyData(['name' => str_repeat('A', 300)])), 'name');
        $this->assertHuman422($this->ajaxPost(route('party.store'), $this->partyData(['address' => str_repeat('x', 1200)])), 'address');
        $this->assertHuman422($this->ajaxPost(route('party.store'), $this->partyData(['name' => "Robert'); DROP TABLE parties;--"])), 'invalid characters');

        $this->assertSame($before, DB::table('parties')->count());
    }

    public function test_expense_validation_missing_negative_and_too_long_input(): void
    {
        $this->signIn('superadmin', 'main');
        $valid = [
            'expense_date' => now()->toDateString(), 'payment_group_id' => $this->coa('cash'), 'payment_account_id' => $this->coa('cash-main'),
            'details' => [['expense_group_id' => $this->coa('operating-expenses'), 'expense_account_id' => $this->coa('rent-expense'), 'amount' => 100]],
        ];

        $this->assertHuman422($this->ajaxPost(route('admin.expense.store'), ['details' => []] + $valid), 'expense line');
        $this->assertHuman422($this->ajaxPost(route('admin.expense.store'), array_merge($valid, ['payment_account_id' => null])), 'payment account');
        $this->assertHuman422($this->ajaxPost(route('admin.expense.store'), array_merge($valid, [
            'details' => [['expense_group_id' => $this->coa('operating-expenses'), 'expense_account_id' => $this->coa('rent-expense'), 'amount' => -50]],
        ])), 'amount');
        $this->assertHuman422($this->ajaxPost(route('admin.expense.store'), array_merge($valid, ['reference_no' => str_repeat('R', 150)])), 'reference');

        $this->assertSame(0, DB::table('expenses')->count());
    }

    public function test_chart_of_accounts_validation_missing_negative_and_too_long_input(): void
    {
        $this->signIn('superadmin', 'main');
        $before = DB::table('chart_of_accounts')->count();

        $this->assertHuman422($this->ajaxPost(route('admin.chart-of-accounts.store'), ['parent_id' => $this->coa('operating-expenses')]), 'account name');
        $this->assertHuman422($this->ajaxPost(route('admin.chart-of-accounts.store'), ['account_name' => 'X']), 'parent');
        $this->assertHuman422($this->ajaxPost(route('admin.chart-of-accounts.store'), [
            'parent_id' => $this->coa('operating-expenses'), 'account_name' => 'Negative', 'opening_balance' => -10,
        ]), 'opening balance');
        $this->assertHuman422($this->ajaxPost(route('admin.chart-of-accounts.store'), [
            'parent_id' => $this->coa('operating-expenses'), 'account_name' => str_repeat('N', 200),
        ]), 'account name');
        $this->assertHuman422($this->ajaxPost(route('admin.chart-of-accounts.store'), ['parent_id' => 999999, 'account_name' => 'Orphan']));

        $this->assertSame($before, DB::table('chart_of_accounts')->count());
    }
}
