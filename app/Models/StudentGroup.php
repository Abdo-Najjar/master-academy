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

    public function campaigns(): HasMany
    {
        return $this->hasMany(WhatsappCampaign::class);
    }
}
