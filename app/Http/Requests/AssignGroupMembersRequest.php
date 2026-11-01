<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignGroupMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign group members') ?? false;
    }

    public function rules(): array
    {
        return [
            'member_ids' => ['required', 'array', 'min:1', 'max:200'],
            'member_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('members', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'member_ids' => __('groups.fields.members'),
            'member_ids.*' => __('groups.fields.member'),
        ];
    }
}
