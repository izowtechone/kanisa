<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit departments') ?? false;
    }

    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('departments', 'code')->ignore($department),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('departments', 'name')->ignore($department),
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
            'code' => __('departments.fields.code'),
            'name' => __('departments.fields.name'),
            'description' => __('departments.fields.description'),
            'leader_member_id' => __('departments.fields.leader'),
            'is_active' => __('departments.fields.status'),
        ];
    }
}
