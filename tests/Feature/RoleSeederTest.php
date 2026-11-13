<?php

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

test('role seeder creates every role and permission and is idempotent', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->map->value->sort()->values()->all())
        ->and(PermissionModel::count())->toBe(count(Permission::cases()));

    $admin = RoleModel::findByName(Role::Admin->value);
    $staff = RoleModel::findByName(Role::Staff->value);
    $instructor = RoleModel::findByName(Role::Instructor->value);

    expect($admin->permissions)->toHaveCount(count(Permission::cases()))
        ->and($staff->hasPermissionTo(Permission::OrdersManage->value))->toBeTrue()
        ->and($staff->hasPermissionTo(Permission::CoursesManage->value))->toBeFalse()
        ->and($instructor->hasPermissionTo(Permission::AssignmentsReview->value))->toBeTrue()
        ->and($instructor->hasPermissionTo(Permission::OrdersManage->value))->toBeFalse();
});
