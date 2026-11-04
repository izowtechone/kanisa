<?php

namespace App\Http\Requests;

use App\Models\FinanceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOfferingCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('category') === null
            ? 'create offering categories'
            : 'edit offering categories';

        return $this->user()?->can($permission)
            || $this->user()?->can('manage finance categories')
            || false;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof FinanceCategory ? $category->id : null;
        $codeUnique = Rule::unique('finance_categories', 'code')->where('type', 'offering');
        $englishNameUnique = Rule::unique('finance_categories', 'name_en')->where('type', 'offering');
        $legacyEnglishNameUnique = Rule::unique('finance_categories', 'name')->where('type', 'offering');
        $swahiliNameUnique = Rule::unique('finance_categories', 'name_sw')->where('type', 'offering');

        if ($categoryId !== null) {
            $codeUnique->ignore($categoryId);
            $englishNameUnique->ignore($categoryId);
            $legacyEnglishNameUnique->ignore($categoryId);
            $swahiliNameUnique->ignore($categoryId);
        }

        return [
            'code' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
                $codeUnique,
            ],
            'name_en' => [
                'required',
                'string',
                'max:150',
                $englishNameUnique,
                $legacyEnglishNameUnique,
            ],
            'name_sw' => [
                'required',
                'string',
                'max:150',
                $swahiliNameUnique,
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => __('finance.category_code'),
            'name_en' => __('finance.category_name_en'),
            'name_sw' => __('finance.category_name_sw'),
            'description' => __('finance.category_description'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : null,
            'name_en' => trim((string) $this->input('name_en')),
            'name_sw' => trim((string) $this->input('name_sw')),
            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,
        ]);
    }
}
