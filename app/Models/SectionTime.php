<?php

namespace App\Models;

use App\Observers\SectionTimeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([SectionTimeObserver::class])]
class SectionTime extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['section_id', 'room_id', 'day', 'start_time', 'end_time'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
