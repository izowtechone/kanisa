<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'membership_number',
        'envelope_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'address',
        'membership_date',
        'status',
        'sms_opt_in',
        'community_id',
        'preferred_language',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'membership_date' => 'date',
            'sms_opt_in' => 'boolean',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MemberAuditLog::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withTimestamps();
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
