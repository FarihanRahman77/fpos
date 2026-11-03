<?php

namespace Tests\Feature\Auth;

use Tests\AppTestCase;

/** Updated from the stock Breeze test: the user signs in to a branch first (SetBranch requires one). Same assertions. */
class PasswordConfirmationTest extends AppTestCase
{
    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $this->signIn('cashier', 'two');

        $this->get('/confirm-password')->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $this->signIn('cashier', 'two');

        $response = $this->post('/confirm-password', ['password' => $this->password]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $this->signIn('cashier', 'two');

        $this->post('/confirm-password', ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));
    }
}
