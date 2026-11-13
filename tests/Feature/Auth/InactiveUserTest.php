<?php

use App\Models\User;

test('inactive users cannot log in and see an Indonesian error', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors([
        'email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi admin.',
    ]);

    $this->assertGuest();
});

test('wrong credentials still show the generic Indonesian error', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'salah',
    ])->assertSessionHasErrors([
        'email' => 'Email atau kata sandi tidak sesuai dengan data kami.',
    ]);

    $this->assertGuest();
});

test('an existing session is logged out once the user is deactivated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('profile.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
