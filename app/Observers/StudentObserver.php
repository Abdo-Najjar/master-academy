<?php

namespace App\Observers;

use App\Models\Student;

class StudentObserver
{
    public function creating(Student $student): void
    {
        if (empty($student->student_number)) {
            do {
                $number = 'STU-'.random_int(100000, 999999);
            } while (Student::query()->withTrashed()->where('student_number', $number)->exists());

            $student->student_number = $number;
        }
    }
}
