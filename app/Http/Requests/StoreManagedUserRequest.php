<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class StoreManagedUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{6,30}$/'],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'role_ids' => ['required', 'array', 'min:1', 'max:20'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $hasAdministrativeRole = Role::query()
                    ->where('guard_name', 'web')
                    ->whereIn('id', $this->input('role_ids', []))
                    ->where(function ($query): void {
                        $query->where('name', User::SUPER_ADMIN_ROLE)
                            ->orWhereHas('permissions', fn ($permissions) => $permissions->whereIn(
                                'name',
                                User::USER_ADMIN_PERMISSIONS,
                            ));
                    })
                    ->exists();

                if ($hasAdministrativeRole) {
                    $validator->errors()->add('role_ids', __('users.new_user_admin_role_denied'));
                }
            },
        ];
    }
}
