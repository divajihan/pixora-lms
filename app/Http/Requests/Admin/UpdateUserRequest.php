<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::UsersManage->value);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => filter_var($this->input('is_active', false), FILTER_VALIDATE_BOOLEAN)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['boolean'],
            'headline' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'peran', 'is_active' => 'status akun', 'headline' => 'headline', 'bio' => 'bio'];
    }
}
