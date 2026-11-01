<?php

namespace App\Http\Requests;

use App\Models\FinanceCategory;
use App\Models\FinancialCampaign;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('finance.envelopes.store')) {
            return (bool) ($this->user()?->can('record envelope offerings')
                || $this->user()?->can('manage offerings'));
        }

        $permission = match ($this->route('type')) {
            'offering' => 'manage offerings',
            'tithe' => 'manage tithes',
            'contribution' => 'manage contributions',
            'other_income' => 'manage other income',
            'expense' => 'manage expenses',
            default => null,
        };

        return $permission !== null && ($this->user()?->can($permission) ?? false);
    }

    public function rules(): array
    {
        $isEnvelope = $this->routeIs('finance.envelopes.store');
        $type = $isEnvelope ? $this->input('type') : $this->route('type');

        return [
            'idempotency_key' => ['required', 'uuid'],
            'type' => [$isEnvelope ? 'required' : 'prohibited', Rule::in(['offering', 'tithe'])],
            'category_id' => ['required', 'integer', 'exists:finance_categories,id'],
            'campaign_id' => [
                $type === 'contribution' ? 'required' : 'prohibited',
                'integer',
                Rule::exists('financial_campaigns', 'id'),
            ],
            'member_id' => ['prohibited'],
            'member_number' => [
                $isEnvelope || ($type === 'contribution' && $this->boolean('is_anonymous')) ? 'prohibited' : 'nullable',
                'string',
                'max:60',
            ],
            'envelope_code' => [$isEnvelope ? 'required' : 'prohibited', 'string', 'max:255'],
            'payee' => [$type === 'expense' ? 'required' : 'prohibited', 'string', 'max:200'],
            'amount' => ['required', 'string', 'regex:/^(?=.*[1-9])\d{1,13}(?:\.\d{1,2})?$/'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'mobile_money', 'cheque', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_anonymous' => [$type === 'contribution' ? 'sometimes' : 'prohibited', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->hasAny(['category_id', 'type', 'campaign_id'])) {
                    return;
                }

                $isEnvelope = $this->routeIs('finance.envelopes.store');
                $type = $isEnvelope ? $this->input('type') : $this->route('type');
                $categoryExists = FinanceCategory::query()
                    ->whereKey($this->integer('category_id'))
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->exists();

                if (! $categoryExists) {
                    $validator->errors()->add('category_id', __('finance.category_type_mismatch'));
                }

                if ($type === 'contribution') {
                    $campaign = FinancialCampaign::query()
                        ->whereKey($this->integer('campaign_id'))
                        ->where('status', 'active')
                        ->first();

                    if ($campaign === null) {
                        $validator->errors()->add('campaign_id', __('finance.campaign_not_active'));
                    } elseif (! $validator->errors()->has('transaction_date')
                        && ! $campaign->acceptsTransactionDate((string) $this->input('transaction_date'))) {
                        $validator->errors()->add('transaction_date', __('finance.campaign_date_outside_window'));
                    }
                }

                if ($isEnvelope) {
                    $memberExists = Member::query()
                        ->where('envelope_number', (string) $this->input('envelope_code'))
                        ->where('status', 'active')
                        ->exists();

                    if (! $memberExists) {
                        $validator->errors()->add('envelope_code', __('finance.envelope_member_not_found'));
                    }

                    return;
                }

                if ($this->filled('member_number')) {
                    $memberIsActive = Member::query()
                        ->where('membership_number', trim((string) $this->input('member_number')))
                        ->where('status', 'active')
                        ->exists();

                    if (! $memberIsActive) {
                        $validator->errors()->add('member_number', __('finance.active_member_required'));
                    }
                }

                if ($type === 'contribution' && ! $this->boolean('is_anonymous') && ! $this->filled('member_number')) {
                    $validator->errors()->add('member_number', __('finance.member_required_or_anonymous'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('finance.envelopes.store') && $this->filled('envelope_code')) {
            $this->merge(['envelope_code' => trim((string) $this->input('envelope_code'))]);
        }
    }
}
