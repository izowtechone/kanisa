<?php

namespace App\Http\Requests;

use App\Rules\MobilePhone;
use Illuminate\Foundation\Http\FormRequest;

class SendManualSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send SMS') ?? false;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/', new MobilePhone],
            'message' => ['required', 'string', 'max:3000'],
            'confirmation' => ['required', 'accepted'],
        ];
    }
}
