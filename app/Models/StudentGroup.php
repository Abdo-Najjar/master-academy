<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentGroup extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name'];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_group_student');
    }

    /**
     * Standalone name+phone contacts on this group that aren't tied to a
     * registered Student record (e.g. parents, external numbers).
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(StudentGroupContact::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(WhatsappCampaign::class);
    }
}
