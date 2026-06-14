<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Section extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'subject_id',
        'trainer_id',
        'start_date',
        'end_date',
        'price',
        'trainer_rate',
        'capacity',
        'section_type',
        'fee_type',
        'sessions_per_fee_cycle',
        'seat_reservation_type',
        'seat_reservation_amount',
    ];

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'price' => 'decimal:2',
            'trainer_rate' => 'decimal:2',
            'seat_reservation_amount' => 'decimal:2',
            'capacity' => 'integer',
            'sessions_per_fee_cycle' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'subject_id', 'trainer_id', 'start_date', 'end_date', 'price', 'trainer_rate', 'capacity'])
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('materials');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function times(): HasMany
    {
        return $this->hasMany(SectionTime::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (! $this->start_date || ! $this->end_date) {
                    return 'scheduled';
                }
                $today = now()->startOfDay();
                if ($this->start_date->isFuture()) {
                    return 'upcoming';
                }
                if ($this->end_date->isPast()) {
                    return 'completed';
                }
                if ($this->start_date->lte($today) && $this->end_date->gte($today)) {
                    return 'active';
                }
                return 'scheduled';
            }
        );
    }

    /**
     * Compute the effective trainer rate (percent), falling back to the trainer's default rate.
     */
    public function effectiveTrainerRate(): float
    {
        if ($this->trainer_rate !== null) {
            return (float) $this->trainer_rate;
        }
        return (float) ($this->trainer?->default_rate ?? 0);
    }
}
