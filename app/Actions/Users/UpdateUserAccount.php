<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin changes a user's role, active state and instructor profile.
 * Guards: the last active admin cannot be demoted or deactivated, and an
 * admin cannot deactivate their own account.
 */
class UpdateUserAccount
{
    /**
     * @param  array{role: string, is_active: bool, headline?: string|null, bio?: string|null}  $data
     */
    public function handle(User $actor, User $user, array $data): User
    {
        $role = Role::from($data['role']);
        $isActive = (bool) $data['is_active'];

        return DB::transaction(function () use ($actor, $user, $role, $isActive, $data): User {
            // Serialise concurrent admin changes so two admins cannot demote each other at once.
            User::role(Role::Admin->value)->lockForUpdate()->get(['id']);

            if ($actor->is($user) && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ]);
            }

            if ($user->isLastActiveAdmin() && ($role !== Role::Admin || ! $isActive)) {
                throw ValidationException::withMessages([
                    'role' => 'Ini adalah akun admin aktif terakhir. Tetapkan admin lain terlebih dahulu sebelum mengubah peran atau menonaktifkannya.',
                ]);
            }

            $user->forceFill(['is_active' => $isActive])->save();
            $user->syncRoles([$role->value]);

            if ($role === Role::Instructor || $user->instructorProfile()->exists()) {
                $user->instructorProfile()->updateOrCreate([], [
                    'headline' => $data['headline'] ?? null,
                    'bio' => $data['bio'] ?? null,
                ]);
            }

            return $user;
        });
    }
}
