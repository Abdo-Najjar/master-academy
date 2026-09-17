<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Branch extends Model
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'governorate_id', 'city_id', 'address', 'show_on_site', 'sort_order'];

    /** @var list<string> */
    public array $translatable = ['name', 'address'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'show_on_site' => 'boolean',
        ];
    }

    /** @param  Builder<Branch>  $query */
    public function scopeOnSite(Builder $query): void
    {
        $query->where('show_on_site', true)->orderBy('sort_order')->orderBy('id');
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Everything else a branch now owns. A branch used to be a label on a
     * section; it is a wall now, and deleting one takes its rooms, its money
     * and its staff out of everybody's sight along with it — so the deletion
     * guard has to be able to see them coming.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    /** Trainers available to teach at this branch. See Trainer::branches(). */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'branch_trainer')->withTimestamps();
    }
}
