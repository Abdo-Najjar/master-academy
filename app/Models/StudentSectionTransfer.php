<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSectionTransfer extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'from_section_id',
        'to_section_id',
        'reason',
        'transferred_by',
        'transferred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    public function toSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
