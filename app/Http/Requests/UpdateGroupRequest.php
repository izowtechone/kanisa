<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit groups') ?? false;
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('groups', 'code')->ignore($group),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('groups', 'name')
                    ->where('department_id', $this->input('department_id'))
                    ->ignore($group),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'leader_member_id' => [
                'nullable',
                'integer',
                Rule::exists('members', 'id')->whereNull('deleted_at'),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? Str::upper(trim($this->input('code'))) : $this->input('code'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'department_id' => __('departments.fields.department'),
            'code' => __('groups.fields.code'),
            'name' => __('groups.fields.name'),
            'description' => __('groups.fields.description'),
            'leader_member_id' => __('groups.fields.leader'),
            'is_active' => __('groups.fields.status'),
        ];
    }
}
