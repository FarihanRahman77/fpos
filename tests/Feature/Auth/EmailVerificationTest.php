<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\AppTestCase;

/**
 * Updated from the stock Breeze test: every signed-in request must carry a logged-in branch (SetBranch signs out
 * sessions without one), so the user signs in to a branch first (signIn()). Assertions are unchanged
 * (the invalid-hash case now also asserts the 403).
 */
class EmailVerificationTest extends AppTestCase
{
    private function unverifiedCashier()
    {
        DB::table('users')->where('id', $this->ids['user.cashier'])->update(['email_verified_at' => null]);

        return $this->user('cashier');
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $this->signIn($this->unverifiedCashier(), 'two');

        $this->get('/verify-email')->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = $this->unverifiedCashier();
        $this->signIn($user, 'two');
        Event::fake();

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $response = $this->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(RouteServiceProvider::HOME . '?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = $this->unverifiedCashier();
        $this->signIn($user, 'two');

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('wrong-email')]);

        $this->get($verificationUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
