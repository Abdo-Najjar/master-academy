<?php

namespace App\Models;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Interfaces\WalletFloat;
use Bavix\Wallet\Traits\HasWalletFloat;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Trainer extends Authenticatable implements FilamentUser, HasAvatar, HasMedia, Wallet, WalletFloat
{
    use HasFactory, HasTranslations, HasWalletFloat, InteractsWithMedia, LogsActivity, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'dob',
        'ssn',
        'username',
        'trainer_number',
        'email',
        'password',
        'phone_number',
        'whatsapp_number',
        'governorate_id',
        'city_id',
        'default_rate',
        'bio',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'dob' => 'date',
            'default_rate' => 'decimal:2',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'trainer';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('main') ?: null;
    }

    public function getFilamentName(): string
    {
        return $this->getTranslation('name', app()->getLocale(), false) ?? '';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'dob', 'ssn', 'username', 'email', 'phone_number', 'whatsapp_number', 'governorate_id', 'city_id', 'trainer_number', 'default_rate'])
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_trainer')->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
