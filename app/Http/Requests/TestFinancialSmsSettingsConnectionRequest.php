<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestFinancialSmsSettingsConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage SMS settings') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
