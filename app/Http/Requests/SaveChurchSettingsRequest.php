<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveChurchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'church_short_name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/',
            ],
            'community_code_prefix' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/',
            ],
            'system_language' => ['required', Rule::in(['en', 'sw'])],
            'church_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'remove_church_logo' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('remove_church_logo') && $this->hasFile('church_logo')) {
                    $validator->errors()->add('church_logo', __('members.choose_logo_action'));
                }
            },
        ];
    }
}
