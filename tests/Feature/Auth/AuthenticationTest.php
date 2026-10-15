<?php

use App\Models\AuditLog;
use App\Models\User;

test('login screen can be rendered', function () {
    $this->get('/login')->assertStatus(200);
});

test('users can sign in with their email', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users can sign in with their phone number in local or international format', function (string $typed) {
    $user = User::factory()->create(['phone' => '+233241234567']);

    $this->post('/login', ['login' => $typed, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
})->with(['024 123 4567', '+233241234567', '0241234567']);

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->email, 'password' => 'wrong-password']);

    $this->assertGuest();
});

test('passwords are stored hashed', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    expect($user->getRawOriginal('password'))->not->toBe('secret-password')
        ->and(password_verify('secret-password', $user->getRawOriginal('password')))->toBeTrue();
});

test('five failed attempts lock the account for 15 minutes', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['login' => $user->email, 'password' => 'wrong']);
    }

    expect($user->fresh()->isLocked())->toBeTrue();

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();

    $this->travel(16)->minutes();

    $this->post('/login', ['login' => $user->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('the lockout is recorded in the audit log', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['login' => $user->email, 'password' => 'wrong']);
    }

    expect(AuditLog::where('entity', 'user')->where('entity_id', $user->id)->where('action', 'updated')->exists())->toBeTrue();
});

test('disabled users cannot sign in', function () {
    $user = User::factory()->disabled()->create();

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('a user disabled mid-session is signed out on their next request', function () {
    $user = User::factory()->warehouse()->create();
    $this->actingAs($user)->get('/stock')->assertOk();

    $user->update(['status' => 'disabled']);

    $this->get('/stock')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('public self-registration is not available', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});
