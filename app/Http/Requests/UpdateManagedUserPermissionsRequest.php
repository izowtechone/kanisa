<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateManagedUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage permissions') ?? false;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['nullable', 'array', 'max:200'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->where('guard_name', 'web')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedIds = $this->user()?->getAllPermissions()->pluck('id')->all() ?? [];
                $requestedIds = array_map('intval', (array) $this->input('permission_ids', []));

                if (array_diff($requestedIds, $allowedIds) !== []) {
                    $validator->errors()->add('permission_ids', __('users.permission_escalation_denied'));
                }
            },
        ];
    }
}
