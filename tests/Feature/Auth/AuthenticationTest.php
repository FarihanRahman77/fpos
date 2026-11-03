<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Admin\BranchController;
use App\Providers\RouteServiceProvider;
use Tests\AppTestCase;

/**
 * Updated from the stock Breeze test. The app's login is branch-wise: the form must name a branch the user is
 * permitted in (BranchController::loginProblem()), the chosen branch is stored once in the session
 * (loggedInBranchId), and inactive / deleted accounts are refused.
 */
class AuthenticationTest extends AppTestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Branch Two'); // the login form lists the active branches
    }

    /** Was: login with email + password only. Now a permitted branch must be chosen and lands in the session. */
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $response = $this->post('/login', [
            'email' => 'cashier@example.test',
            'password' => $this->password,
            'branch_id' => $this->branch('two'),
        ]);

        $this->assertAuthenticated();
        $this->assertAuthenticatedAs($this->user('cashier'));
        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertSame($this->branch('two'), session(BranchController::SESSION_KEY));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'cashier@example.test',
            'password' => 'wrong-password',
            'branch_id' => $this->branch('two'),
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /** New: no branch chosen → refused with a branch_id error. */
    public function test_login_requires_a_branch(): void
    {
        $response = $this->post('/login', ['email' => 'cashier@example.test', 'password' => $this->password]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['branch_id' => 'Choose the branch you are working in.']);
        $this->assertNull(session(BranchController::SESSION_KEY));
    }

    /** New: a branch the user is not permitted in is refused (cashier works only in Branch Two). */
    public function test_login_to_a_branch_the_user_is_not_permitted_in_is_refused(): void
    {
        $response = $this->post('/login', ['email' => 'cashier@example.test', 'password' => $this->password, 'branch_id' => $this->branch('main')]);

        $this->assertGuest();
        $response->assertSessionHasErrors('branch_id');
        $this->assertStringContainsString('not allowed in Main Branch', session('errors')->first('branch_id'));
    }

    /** New: an inactive branch cannot be signed in to, even by a user with all branches. */
    public function test_login_to_an_inactive_branch_is_refused(): void
    {
        \DB::table('branches')->where('id', $this->branch('three'))->update(['status' => 'Inactive']);

        $this->post('/login', ['email' => 'superadmin@example.test', 'password' => $this->password, 'branch_id' => $this->branch('three')])
            ->assertSessionHasErrors('branch_id');
        $this->assertGuest();
    }

    /** New: inactive account refused even with the right password and branch. */
    public function test_inactive_user_cannot_log_in(): void
    {
        $response = $this->post('/login', ['email' => 'inactive@example.test', 'password' => $this->password, 'branch_id' => $this->branch('main')]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['branch_id' => 'Your account is inactive. Ask an administrator to activate it.']);
    }

    /** New (regression): a soft-deleted user (deleted = Yes) cannot log in. */
    public function test_deleted_user_cannot_log_in(): void
    {
        $response = $this->post('/login', ['email' => 'deleted@example.test', 'password' => $this->password, 'branch_id' => $this->branch('main')]);

        $this->assertGuest();
        $response->assertSessionHasErrors('branch_id');
    }

    /** Was: actingAs($user)->post('/logout') with a factory user; now signed in to a branch first. Logout redirects to '/'. */
    public function test_users_can_logout(): void
    {
        $this->signIn('cashier', 'two');

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
        $this->assertNull(session(BranchController::SESSION_KEY));
    }

    /** New: SetBranch signs out a session that has no loggedInBranchId (e.g. restored from remember-me). */
    public function test_session_without_branch_is_signed_out(): void
    {
        $response = $this->actingAs($this->user('superadmin'))->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['branch_id' => 'Choose your branch and sign in again.']);
        $this->assertGuest();
    }

    /** New: a JSON request without a branch gets 401 JSON instead of a redirect. */
    public function test_ajax_session_without_branch_gets_401_json(): void
    {
        $this->actingAs($this->user('superadmin'))->ajaxGet('/admin/sales/list')
            ->assertStatus(401)->assertJson(['status' => false]);
        $this->assertGuest();
    }

    /** New: a session whose branch access was removed while signed in is signed out on the next request. */
    public function test_session_is_signed_out_when_branch_access_is_removed(): void
    {
        $this->signIn('manager', 'two');
        $this->get('/admin/sales')->assertOk();

        \DB::table('branch_user')->where('user_id', $this->ids['user.manager'])->where('branch_id', $this->branch('two'))->delete();

        $this->get('/admin/sales')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** New: a user deactivated while signed in is signed out on the next request. */
    public function test_session_is_signed_out_when_user_is_deactivated(): void
    {
        $this->signIn('cashier', 'two');
        \DB::table('users')->where('id', $this->ids['user.cashier'])->update(['status' => 'Inactive']);
        // a real request reloads the user from the database; actingAs() keeps the in-memory model, so reload it
        $this->actingAs($this->user('cashier'));

        $this->get('/admin/sales/pos')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
