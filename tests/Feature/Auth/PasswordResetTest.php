<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('the forgot password screen can be rendered', function () {
    $this->get(route('password.request'))->assertOk();
});

test('a reset link is emailed to an active user', function () {
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('the reply is the same for unknown, disabled and system accounts, and nothing is sent', function () {
    $disabled = User::factory()->disabled()->create();
    $system = User::systemAccount();
    $message = 'If that email belongs to an active account, a link to reset the password is on its way.';

    foreach (['nobody@bluefoods.test', $disabled->email, User::SYSTEM_EMAIL] as $email) {
        $this->post(route('password.email'), ['email' => $email])->assertSessionHas('status', $message);
    }

    Notification::assertNotSentTo([$disabled, $system], ResetPassword::class);
});

test('the reset screen can be rendered from the emailed link', function () {
    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();

        return true;
    });
});

test('the password can be reset with a valid token, which also unlocks the account', function () {
    $user = User::factory()->create();
    $user->forceFill(['locked_until' => now()->addMinutes(10), 'failed_login_attempts' => 3])->save();
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    $user->refresh();
    expect(password_verify('brand-new-pass', $user->password))->toBeTrue()
        ->and($user->isLocked())->toBeFalse()
        ->and($user->failed_login_attempts)->toBe(0);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'brand-new-pass']);
    $this->assertAuthenticatedAs($user);
});

test('an invalid token does not change the password', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasErrors('email');

    expect(password_verify('password', $user->fresh()->password))->toBeTrue();
});
