<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberImport extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'preview_path',
        'report_path',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'imported_rows',
        'failed_rows',
        'skipped_rows',
        'status',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
