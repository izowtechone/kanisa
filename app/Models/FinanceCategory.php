<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCategory extends Model
{
    protected $fillable = [
        'type',
        'code',
        'name',
        'name_en',
        'name_sw',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function localizedName(?string $locale = null): string
    {
        $name = $locale === 'sw' || ($locale === null && app()->getLocale() === 'sw')
            ? $this->name_sw
            : $this->name_en;

        return filled($name) ? $name : (string) $this->name;
    }
}
