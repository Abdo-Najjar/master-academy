<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['section_id', 'name', 'date', 'max_score', 'note', 'grades_published_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'max_score' => 'decimal:2',
            'grades_published_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ExamGrade::class);
    }

    public function isGradesPublished(): bool
    {
        return $this->grades_published_at !== null;
    }
}
