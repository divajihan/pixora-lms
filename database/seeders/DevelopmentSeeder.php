<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One account per role plus demo catalog data. Development only.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentSeeder must never run in production.');
        }

        $this->account(Role::Admin, 'Admin Pixora', (string) config('pixora.seed.admin_email'), (string) config('pixora.seed.admin_password'));
        $this->account(Role::Staff, 'Staf Pixora', 'staff@pixora.test');
        $this->account(Role::Instructor, 'Instruktur Pixora', 'instructor@pixora.test');
        $this->account(Role::Student, 'Siswa Pixora', 'student@pixora.test');

        $this->call(CatalogSeeder::class);
        $this->call(LearningSeeder::class);
        $this->call(AssignmentSeeder::class);

        $this->command?->table(['Peran', 'Email', 'Kata sandi'], [
            ['admin', config('pixora.seed.admin_email'), '(SEED_ADMIN_PASSWORD)'],
            ['staff', 'staff@pixora.test', 'password'],
            ['instructor', 'instructor@pixora.test', 'password'],
            ['student', 'student@pixora.test', 'password'],
            ['student (demo)', 'siswa1..8@pixora.test', 'password'],
        ]);
    }

    private function account(Role $role, string $name, string $email, string $password = 'password'): User
    {
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $password,
        ]);

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now(), 'is_active' => true])->save();
        $user->syncRoles([$role->value]);

        return $user;
    }
}
