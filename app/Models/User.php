<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'must_reset_password',
        'photo_path',
        'date_of_birth',
        'gender',
        'phone',
        'address',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'must_reset_password' => 'boolean',
            'date_of_birth' => 'date',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_set_at' => 'datetime',
        ];
    }

    /**
     * Keep password_set_at honest without every caller remembering it: saving a password on an
     * account that is (or becomes) Active means a person now knows it — they chose it, or were
     * handed it. Pending and Inactive saves are skipped because those are the random placeholders
     * written at creation and at erasure. Callers that set a known password on an Inactive account
     * (a completed reset link, the Admin's Set password) stamp the column themselves.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('password') && ! $user->isDirty('password_set_at')
                && ! in_array($user->status, ['Pending', 'Inactive'], true)) {
                $user->password_set_at = now();
            }
        });
    }

    /** Erased under a data-erasure request (Admin Delete fallback or a retention action). */
    public function isErased(): bool
    {
        return str_ends_with((string) $this->email, '@deleted.yasis.edu')
            || str_ends_with((string) $this->email, '@retention.invalid');
    }

    /** A login account at all: HR staff records created without portal access hold no role. */
    public function hasPortalAccess(): bool
    {
        return $this->roles->isNotEmpty();
    }

    /** Has anyone ever been given a working password for this account? */
    public function hasKnownPassword(): bool
    {
        return $this->password_set_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class, 'id', 'id');
    }

    public function guardian()
    {
        return $this->hasOne(Guardian::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
