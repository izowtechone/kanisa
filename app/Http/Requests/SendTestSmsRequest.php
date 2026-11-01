<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendTestSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send test SMS') ?? false;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
            'type' => ['required', Rule::in(['offering', 'contribution'])],
            'language' => ['required', Rule::in(['en', 'sw'])],
            'confirmation' => ['required', 'accepted'],
        ];
    }
}
