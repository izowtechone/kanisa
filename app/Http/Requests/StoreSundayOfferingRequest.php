<?php

namespace App\Http\Requests;

use App\Models\FinanceCategory;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSundayOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create Sunday offerings') ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'category_id' => ['required', 'integer', 'exists:finance_categories,id'],
            'amount' => ['required', 'string', 'regex:/^(?=.*[1-9])\d{1,13}(?:\.\d{1,2})?$/'],
            'payment_method' => ['prohibited'],
            'transaction_date' => ['prohibited'],
            'reference_number' => ['prohibited'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('category_id')) {
                    $categoryIsActive = FinanceCategory::query()
                        ->whereKey($this->integer('category_id'))
                        ->where('type', 'offering')
                        ->where('is_active', true)
                        ->exists();

                    if (! $categoryIsActive) {
                        $validator->errors()->add('category_id', __('finance.category_type_mismatch'));
                    }
                }

                if (! $validator->errors()->has('member_id')) {
                    $memberIsActive = Member::query()
                        ->whereKey($this->integer('member_id'))
                        ->where('status', 'active')
                        ->exists();

                    if (! $memberIsActive) {
                        $validator->errors()->add('member_id', __('finance.active_member_required'));
                    }
                }
            },
        ];
    }
}
