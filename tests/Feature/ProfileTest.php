<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;

/**
 * Updated from the stock Breeze test.
 * - The user signs in to a branch first (SetBranch signs out sessions without one).
 * - The app's profile page is admin.users.profile/{id} (own profile only); /profile (Breeze) is still routed for the
 *   update / delete actions.
 * - Deleting an account is a soft delete (users.deleted = Yes, the row stays for the records the user made), and the
 *   last active account cannot delete itself.
 */
class ProfileTest extends AppTestCase
{
    /** Was: GET /profile is 200. Now: the app's profile page (admin.users.profile) shows the user's own profile. */
    public function test_profile_page_is_displayed(): void
    {
        $this->signIn('cashier', 'two');

        $this->get(route('admin.users.profile', $this->ids['user.cashier']))
            ->assertOk()->assertSee('Cashier Two')->assertSee('Branch Two');
    }

    /** New: someone else's profile id redirects to your own. */
    public function test_profile_of_another_user_redirects_to_own(): void
    {
        $this->signIn('cashier', 'two');

        $this->get(route('admin.users.profile', $this->ids['user.superadmin']))
            ->assertRedirect(route('admin.users.profile', $this->ids['user.cashier']));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->signIn('cashier', 'two');

        $this->patch('/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user = $this->user('cashier');
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $this->signIn('cashier', 'two');

        $this->patch('/profile', ['name' => 'Test User', 'email' => 'cashier@example.test'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertNotNull($this->user('cashier')->email_verified_at);
    }

    /** Was: the user row is gone (hard delete). Now: soft delete (deleted = Yes), signed out, and cannot log in again. */
    public function test_user_can_delete_their_account(): void
    {
        $this->signIn('cashier', 'two');

        $this->delete('/profile', ['password' => $this->password])
            ->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $this->ids['user.cashier'], 'deleted' => 'Yes']);

        $this->post('/login', ['email' => 'cashier@example.test', 'password' => $this->password, 'branch_id' => $this->branch('two')])
            ->assertSessionHasErrors('branch_id');
        $this->assertGuest();
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $this->signIn('cashier', 'two');

        $this->from('/profile')->delete('/profile', ['password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')->assertRedirect('/profile');

        $this->assertDatabaseHas('users', ['id' => $this->ids['user.cashier'], 'deleted' => 'No']);
    }

    /** New: the last active account cannot delete itself (nobody could sign in afterwards). */
    public function test_last_active_user_cannot_delete_their_account(): void
    {
        DB::table('users')->where('id', '!=', $this->ids['user.superadmin'])->update(['status' => 'Inactive']);
        $this->signIn('superadmin', 'main');

        $this->from('/profile')->delete('/profile', ['password' => $this->password])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['id' => $this->ids['user.superadmin'], 'deleted' => 'No']);
    }
}
