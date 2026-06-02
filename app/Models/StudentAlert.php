<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAlert extends Model
{
    public const KIND_ABSENCE = 'absence';
    public const KIND_UNPAID = 'unpaid_attendance';

    protected $fillable = [
        'student_id',
        'section_id',
        'kind',
        'threshold_value',
        'payload',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'notified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
