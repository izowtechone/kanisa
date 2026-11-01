<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveFinancialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage financial settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }
}
