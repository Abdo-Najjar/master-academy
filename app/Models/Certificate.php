<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'section_id',
        'template_id',
        'issued_by',
        'serial_number',
        'verification_token',
        'issued_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $cert): void {
            if (empty($cert->serial_number)) {
                $year = now()->year;
                $next = (int) static::query()->withTrashed()->whereYear('created_at', $year)->count() + 1;
                $cert->serial_number = 'CERT-'.$year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            }
            if (empty($cert->verification_token)) {
                $cert->verification_token = Str::uuid()->toString();
            }
            if (empty($cert->issued_at)) {
                $cert->issued_at = now();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
