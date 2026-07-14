<?php

namespace App\Models;

use App\Observers\RegistrationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(RegistrationObserver::class)]
class Registration extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'section_id',
        'payment_type_id',
        'amount_due',
        'amount_paid',
        'exemption_amount',
        'exemption_type_id',
        'trainer_amount',
        'financial_status',
        'seat_reservation_paid',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'exemption_amount' => 'decimal:2',
            'trainer_amount' => 'decimal:2',
            'seat_reservation_paid' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['student_id', 'section_id', 'payment_type_id', 'amount_due', 'amount_paid', 'exemption_amount', 'trainer_amount', 'note'])
            ->logOnlyDirty();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function exemptionType(): BelongsTo
    {
        return $this->belongsTo(ExemptionType::class);
    }

    /**
     * Reverse wallet movements then soft-delete the registration.
     */
    public function deleteWithWalletAdjustments(): void
    {
        DB::transaction(function (): void {
            $this->loadMissing(['student', 'section.trainer']);
            $student = $this->student;
            $trainer = $this->section?->trainer;

            if ($student && (float) $this->amount_paid > 0) {
                $student->depositFloat((float) $this->amount_paid, [
                    'description' => __('Refund for cancelled registration: :name', ['name' => $this->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$this->section_id]),
                    'note' => __('Registration #:id cancelled', ['id' => $this->id]),
                ]);
            }

            if ($trainer && (float) $this->trainer_amount > 0) {
                $trainer->forceWithdrawFloat((float) $this->trainer_amount, [
                    'description' => __('Refund for cancelled registration: :name', ['name' => $this->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$this->section_id]),
                    'note' => __('Registration #:id cancelled', ['id' => $this->id]),
                ]);
            }

            $this->delete();
        });
    }
}
