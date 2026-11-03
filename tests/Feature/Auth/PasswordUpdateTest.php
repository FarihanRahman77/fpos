<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Hash;
use Tests\AppTestCase;

/**
 * Updated from the stock Breeze test: the user signs in to a branch first (SetBranch requires one) and the page they
 * come from is their own profile (admin.users.profile — the app's profile screen). Same assertions, plus the
 * password is unchanged when the current one is wrong.
 */
class PasswordUpdateTest extends AppTestCase
{
    public function test_password_can_be_updated(): void
    {
        $this->signIn('cashier', 'two');
        $from = route('admin.users.profile', $this->ids['user.cashier']);

        $response = $this->from($from)->put('/password', [
            'current_password' => $this->password,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect($from);
        $this->assertTrue(Hash::check('new-password', $this->user('cashier')->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $this->signIn('cashier', 'two');
        $from = route('admin.users.profile', $this->ids['user.cashier']);

        $response = $this->from($from)->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'current_password')->assertRedirect($from);
        $this->assertTrue(Hash::check($this->password, $this->user('cashier')->password));
    }
}
