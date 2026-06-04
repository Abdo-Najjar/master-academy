<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class PaymentType extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name'];

    /** @var list<string> */
    public array $translatable = ['name'];
}
