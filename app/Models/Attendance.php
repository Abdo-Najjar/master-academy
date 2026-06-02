<?php

namespace App\Models;

use App\Observers\AttendanceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([AttendanceObserver::class])]
class Attendance extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['section_id', 'student_id', 'date', 'status', 'is_makeup', 'makeup_for_date', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_makeup' => 'boolean',
            'makeup_for_date' => 'date',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
