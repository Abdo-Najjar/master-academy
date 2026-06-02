<?php

namespace App\Models;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Interfaces\WalletFloat;
use Bavix\Wallet\Traits\HasWalletFloat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Student extends Authenticatable implements HasMedia, Wallet, WalletFloat
{
    use HasFactory, HasTranslations, HasWalletFloat, InteractsWithMedia, LogsActivity, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'dob',
        'ssn',
        'username',
        'student_number',
        'email',
        'password',
        'phone_number',
        'whatsapp_number',
        'parent_name',
        'parent_phone',
        'parent_whatsapp',
        'governorate_id',
        'city_id',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    protected static function booted(): void
    {
        static::creating(function (self $student) {
            if (empty($student->student_number)) {
                $next = (int) static::query()->withTrashed()->max('id') + 1;
                $student->student_number = 'STU-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'dob', 'ssn', 'username', 'email', 'phone_number', 'whatsapp_number', 'parent_name', 'parent_phone', 'parent_whatsapp', 'governorate_id', 'city_id', 'student_number'])
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

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function examGrades(): HasMany
    {
        return $this->hasMany(ExamGrade::class);
    }

    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'complainable');
    }

    public function loginActivities(): MorphMany
    {
        return $this->morphMany(LoginActivity::class, 'auth');
    }

    public function dismissedAnnouncements(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_dismissals')
            ->withPivot('dismissed_at');
    }
}
