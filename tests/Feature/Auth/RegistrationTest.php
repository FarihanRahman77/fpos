<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\AppTestCase;

/**
 * Updated from the stock Breeze test. Public self sign-up was removed on purpose: accounts are created by an
 * administrator (Users screen), who also sets the role and branch access. So /register must not exist and a
 * POST to it must not create or sign in anybody.
 */
class RegistrationTest extends AppTestCase
{
    /** Was: registration screen renders (200). Now (regression): the register route is not available (404). */
    public function test_registration_route_is_not_available(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    /** Was: new users can register and are signed in. Now: POST /register is 404, creates no user, signs nobody in. */
    public function test_new_users_cannot_register(): void
    {
        $before = DB::table('users')->count();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertSame($before, DB::table('users')->count());
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
