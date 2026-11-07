<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'status', 'member_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    public const SUPER_ADMIN_ROLE = 'Super Admin';

    public const USER_ADMIN_PERMISSIONS = [
        'view users',
        'create users',
        'edit users',
        'delete users',
        'manage roles',
        'assign roles',
        'manage permissions',
        'deactivate users',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'password_reset_requested_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function passwordResetInitiator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'password_reset_requested_by');
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(FinancialSmsLog::class);
    }

    /**
     * Invitation tracking needs the additive columns from the Super Admin audit migration;
     * until it is applied the user pages must degrade instead of querying missing columns.
     */
    public static function supportsInvitationTracking(): bool
    {
        return Schema::hasColumn('financial_sms_logs', 'user_id')
            && Schema::hasColumn('users', 'password_reset_requested_by')
            && Schema::hasColumn('users', 'password_reset_requested_at');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN_ROLE);
    }
}
