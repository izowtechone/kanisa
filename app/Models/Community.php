<?php

namespace App\Models;

use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'location',
        'leader_member_id',
        'contact_phone',
        'contact_email',
        'status',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'leader_member_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
