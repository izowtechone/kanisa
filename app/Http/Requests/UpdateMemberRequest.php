<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit members') ?? false;
    }

    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'envelope_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('members', 'envelope_number')->ignore($member),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'membership_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'envelope_number.unique' => __('members.envelope_number_taken'),
            'gender.in' => __('members.invalid_gender'),
            'status.in' => __('members.invalid_status'),
            'date_of_birth.date_format' => __('members.invalid_date'),
            'membership_date.date_format' => __('members.invalid_date'),
            'required' => __('members.validation_required'),
            'string' => __('members.validation_string'),
            'email' => __('members.validation_email'),
            'max' => __('members.validation_max'),
            'date_format' => __('members.validation_date_format'),
            'before_or_equal' => __('members.validation_before_or_equal'),
            'in' => __('members.validation_in'),
        ];
    }
}
