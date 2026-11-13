<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateStaffAccount;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin user management. Route middleware: `permission:users.manage`.
 */
class UserController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $search = Str::of((string) $request->query('q', ''))->squish()->limit(100, '')->toString();
        $role = Role::tryFrom((string) $request->query('peran', ''));

        $users = User::query()
            ->with(['roles:id,name', 'instructorProfile:id,user_id,headline,bio'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($search).'%'])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($search).'%'])))
            ->when($role, fn (Builder $query, Role $role) => $query->role($role->value))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'is_active' => $user->is_active,
                'email_verified' => $user->email_verified_at !== null,
                'headline' => $user->instructorProfile?->headline,
                'bio' => $user->instructorProfile?->bio,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['q' => $search ?: null, 'peran' => $role?->value],
            'roles' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
        ]);
    }

    public function store(StoreUserRequest $request, CreateStaffAccount $createAccount): RedirectResponse
    {
        $result = $createAccount->handle(
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            Role::from((string) $request->validated('role')),
        );

        // Shown once in the UI; never stored in plain text.
        Inertia::flash('temporaryPassword', [
            'name' => $result['user']->name,
            'email' => $result['user']->email,
            'password' => $result['temporary_password'],
        ]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAccount $updateAccount): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        /** @var array{role: string, is_active: bool, headline?: string|null, bio?: string|null} $data */
        $data = $request->validated();

        $updateAccount->handle($actor, $user, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$user->name} diperbarui."]);

        return back();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
