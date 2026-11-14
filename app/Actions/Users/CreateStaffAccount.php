<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin creates an instructor or staff account with a random temporary
 * password (shown once to the admin, who hands it over). The email is marked
 * verified because an admin created it; the user should change the password
 * in Settings → Security after the first login.
 */
class CreateStaffAccount
{
    /**
     * @return array{user: User, temporary_password: string}
     */
    public function handle(string $name, string $email, Role $role): array
    {
        $password = Str::password(14, symbols: false);

        $user = DB::transaction(function () use ($name, $email, $role, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->forceFill(['email_verified_at' => now(), 'is_active' => true])->save();
            $user->syncRoles([$role->value]);

            if ($role === Role::Instructor) {
                $user->instructorProfile()->create();
            }

            return $user;
        });

        return ['user' => $user, 'temporary_password' => $password];
    }
}
