<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A registration enquiry submitted from the public join form. Deliberately not
 * a Student yet — the admin reviews it, then converts it into a real student
 * record once the details are confirmed by phone.
 */
class JoinApplication extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'reference',
        'full_name',
        'phone',
        'age',
        'gender',
        'program_id',
        'program_name',
        'branch_id',
        'contact_preference',
        'notes',
        'source',
        'status',
        'admin_notes',
        'student_id',
        'handled_by',
        'handled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $application): void {
            $application->reference ??= strtoupper(Str::random(8));
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The student record created from this application, once converted. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return array<string, string> status value => translated label. */
    public static function statusOptions(): array
    {
        return [
            'new' => __('New'),
            'contacted' => __('Contacted'),
            'enrolled' => __('Enrolled'),
            'rejected' => __('Rejected'),
        ];
    }

    /** @return array<string, string> contact preference value => translated label. */
    public static function contactPreferenceOptions(): array
    {
        return [
            'whatsapp' => __('WhatsApp'),
            'phone' => __('Phone Call'),
        ];
    }

    /** The program the visitor asked for, whether picked from the list or typed. */
    public function getRequestedProgramAttribute(): string
    {
        return $this->program?->title ?? ($this->program_name ?: __('Not specified'));
    }

    /** @param  Builder<JoinApplication>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'new');
    }
}
