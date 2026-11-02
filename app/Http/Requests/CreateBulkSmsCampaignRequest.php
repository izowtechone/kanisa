<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateBulkSmsCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send bulk SMS') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'language' => ['required', Rule::in(['en', 'sw'])],
            'selection_mode' => ['required', Rule::in(['selected', 'filters'])],
            'member_ids' => ['exclude_unless:selection_mode,selected', 'required_if:selection_mode,selected', 'array', 'min:1', 'max:500'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists('members', 'id')],
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:registered_from'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'confirmation' => ['required', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['department_id', 'group_id'])
                    || ! $this->filled('department_id')
                    || ! $this->filled('group_id')) {
                    $this->validateMessagePlaceholders($validator);

                    return;
                }

                $belongs = Group::query()
                    ->whereKey($this->integer('group_id'))
                    ->where('department_id', $this->integer('department_id'))
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('group_id', __('finance.sms_group_department_mismatch'));
                }

                $this->validateMessagePlaceholders($validator);
            },
        ];
    }

    private function validateMessagePlaceholders(Validator $validator): void
    {
        if ($validator->errors()->has('message')) {
            return;
        }

        preg_match_all('/\{\{([^{}]+)\}\}/', (string) $this->input('message'), $matches);

        foreach ($matches[1] as $placeholder) {
            if (! in_array($placeholder, ['member_name', 'member_number', 'church_name'], true)) {
                $validator->errors()->add('message', __('finance.sms_campaign_placeholder_invalid'));

                return;
            }
        }

        // SwalaSMS treats any remaining {tag} as a personalization tag, so stray braces are rejected.
        $withoutPlaceholders = preg_replace('/\{\{(member_name|member_number|church_name)\}\}/', '', (string) $this->input('message'));

        if (preg_match('/[{}]/', (string) $withoutPlaceholders) === 1) {
            $validator->errors()->add('message', __('finance.sms_campaign_placeholder_invalid'));
        }
    }
}
