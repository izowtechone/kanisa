<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage finance categories') ?? false;
    }

    public function rules(): array
    {
        $type = (string) $this->route('type');
        $category = $this->route('category');

        return [
            'type' => ['prohibited'],
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('finance_categories', 'code')
                    ->where('type', $type)
                    ->ignore(is_object($category) ? $category->id : $category),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('finance_categories', 'name')
                    ->where('type', $type)
                    ->ignore(is_object($category) ? $category->id : $category),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
