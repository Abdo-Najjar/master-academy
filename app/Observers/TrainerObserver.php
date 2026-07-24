<?php

namespace App\Observers;

use App\Models\Trainer;

class TrainerObserver
{
    public function creating(Trainer $trainer): void
    {
        if (empty($trainer->trainer_number)) {
            do {
                $number = 'TRN-'.random_int(100000, 999999);
            } while (Trainer::query()->withTrashed()->where('trainer_number', $number)->exists());

            $trainer->trainer_number = $number;
        }
    }
}
