<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\AppTestCase;

/** Stock Breeze test moved onto the app fixtures (a seeded user instead of the factory); also checks the new hash. */
class PasswordResetTest extends AppTestCase
{
    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'cashier@example.test']);

        Notification::assertSentTo($this->user('cashier'), ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();
        $user = $this->user('cashier');

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $this->get('/reset-password/' . $notification->token)->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();
        $user = $this->user('cashier');

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));

            return true;
        });
    }
}
