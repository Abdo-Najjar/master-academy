<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    /**
     * Deliberately *not* using BelongsToBranch.
     *
     * That trait asks BranchContext which branch the signed-in employee is in,
     * and BranchContext answers by asking the auth guard for the user — which
     * runs a User query, which fires the scope again. Employees are filtered on
     * their own screen instead (UserResource), where there is no guard to
     * re-enter.
     */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'password',
        'phone_number',
        'whatsapp_number',
        'ssn',
        'avatar_url',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->is_active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->id === (int) config('app.super_admin_id', 1);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::url($this->avatar_url) : null;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone_number', 'whatsapp_number', 'ssn', 'avatar_url'])
            ->logOnlyDirty();
    }

    /** The site this employee works at; empty means head office — all of them. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function loginActivities(): MorphMany
    {
        return $this->morphMany(LoginActivity::class, 'auth');
    }
}
