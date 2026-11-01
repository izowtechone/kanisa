<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveReceiptSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage receipts') ?? false;
    }

    public function rules(): array
    {
        return [
            'receipt_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }
}
