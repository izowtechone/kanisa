<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialImport extends Model
{
    protected $fillable = [
        'uuid',
        'uploaded_by',
        'category_id',
        'file_hash',
        'file_name',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'rows',
    ];

    protected function casts(): array
    {
        return [
            'rows' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }
}
