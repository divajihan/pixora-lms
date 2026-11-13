<?php

use App\Enums\Role;
use App\Models\User;

test('public registration creates a user with the student role only', function () {
    $this->post(route('register.store'), [
        'name' => 'Siswa Baru',
        'email' => 'siswa@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'siswa@example.com')->firstOrFail();

    expect($user->hasRole(Role::Student->value))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([Role::Student->value])
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

test('validation errors are returned in Indonesian', function () {
    $this->post(route('register.store'), [
        'name' => '',
        'email' => 'bukan-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors([
        'name' => 'Nama wajib diisi.',
        'email' => 'Email harus berupa alamat email yang valid.',
    ]);
});
