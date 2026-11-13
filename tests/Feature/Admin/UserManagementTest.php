<?php

use App\Enums\Role;
use App\Models\User;

test('admin creates an instructor with a one-time temporary password', function () {
    actingAsRole(Role::Admin);

    $response = $this->post(route('admin.users.store'), [
        'name' => 'Instruktur Baru',
        'email' => 'baru@pixora.test',
        'role' => 'instructor',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'baru@pixora.test')->firstOrFail();
    $flash = $response->baseResponse->getSession()->get('inertia.flash_data', [])['temporaryPassword']
        ?? session('inertia.flash_data.temporaryPassword');

    expect($user->hasRole('instructor'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->instructorProfile)->not->toBeNull()
        ->and($flash['password'] ?? null)->toBeString()
        ->and(Hash::check($flash['password'], $user->password))->toBeTrue();

    // The new account can log in with the temporary password.
    auth()->logout();
    $this->post(route('login.store'), ['email' => 'baru@pixora.test', 'password' => $flash['password']]);
    $this->assertAuthenticatedAs($user);
});

test('admins can only create instructor or staff accounts', function () {
    actingAsRole(Role::Admin);

    $this->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@pixora.test', 'role' => 'admin'])
        ->assertSessionHasErrors('role');
});

test('admin changes role, deactivates and edits the instructor profile', function () {
    actingAsRole(Role::Admin);
    $user = User::factory()->create()->assignRole('student');

    $this->put(route('admin.users.update', $user), [
        'role' => 'instructor',
        'is_active' => false,
        'headline' => 'Fotografer',
        'bio' => 'Bio singkat.',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->getRoleNames()->all())->toBe(['instructor'])
        ->and($user->is_active)->toBeFalse()
        ->and($user->instructorProfile->headline)->toBe('Fotografer');
});

test('the last active admin cannot be demoted or deactivated', function (array $payload) {
    $admin = actingAsRole(Role::Admin);
    $other = User::factory()->create()->assignRole('admin');
    $other->forceFill(['is_active' => false])->save(); // inactive admins do not count

    $this->put(route('admin.users.update', $admin), $payload)->assertSessionHasErrors();

    expect($admin->refresh()->hasRole('admin'))->toBeTrue()->and($admin->is_active)->toBeTrue();
})->with([
    'demote' => [['role' => 'staff', 'is_active' => true]],
    'deactivate (self)' => [['role' => 'admin', 'is_active' => false]],
]);

test('another admin cannot demote the last active admin either', function () {
    $last = User::factory()->create()->assignRole('admin');
    $inactiveAdmin = User::factory()->inactive()->create()->assignRole('admin');

    // Acting user is an admin whose session is checked by role middleware; make them active admin #2.
    $actor = User::factory()->create()->assignRole('admin');
    $this->actingAs($actor);
    $this->put(route('admin.users.update', $actor), ['role' => 'staff', 'is_active' => true])->assertSessionHasNoErrors();

    // Now $last is the only active admin; the (now staff) actor can no longer reach the page.
    $this->actingAs($actor->fresh());
    $this->put(route('admin.users.update', $last), ['role' => 'staff', 'is_active' => true])->assertForbidden();
    expect($last->refresh()->hasRole('admin'))->toBeTrue()
        ->and($inactiveAdmin->refresh()->is_active)->toBeFalse();
});

test('with two active admins one can be demoted', function () {
    actingAsRole(Role::Admin);
    $other = User::factory()->create()->assignRole('admin');

    $this->put(route('admin.users.update', $other), ['role' => 'staff', 'is_active' => true])->assertSessionHasNoErrors();

    expect($other->refresh()->getRoleNames()->all())->toBe(['staff']);
});

test('the last active admin cannot delete their own account', function () {
    $admin = actingAsRole(Role::Admin);

    $this->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'Akun admin aktif terakhir tidak dapat dihapus. Tetapkan admin lain terlebih dahulu.']);

    expect(User::find($admin->id))->not->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

test('an admin who is not the last one can delete their own account', function () {
    $admin = actingAsRole(Role::Admin);
    User::factory()->create()->assignRole('admin');

    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasNoErrors();

    expect(User::find($admin->id))->toBeNull();
});
