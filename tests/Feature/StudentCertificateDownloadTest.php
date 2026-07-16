<?php

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;

beforeEach(function () {
    $trainer = Trainer::create(['name' => 'مدرب شهادات', 'username' => 'cert_dl_trainer', 'password' => 'password', 'is_active' => true]);
    $subject = Subject::create(['name' => 'مادة شهادات']);
    $section = Section::create(['name' => 'شعبة شهادات', 'trainer_id' => $trainer->id, 'subject_id' => $subject->id, 'price' => 100]);

    $this->owner = Student::create(['name' => 'مالك الشهادة', 'student_number' => 'STU-CERT-1', 'password' => 'password', 'is_active' => true]);
    $this->outsider = Student::create(['name' => 'طالب آخر', 'student_number' => 'STU-CERT-2', 'password' => 'password', 'is_active' => true]);

    Registration::create(['section_id' => $section->id, 'student_id' => $this->owner->id]);

    $template = CertificateTemplate::create(['name' => 'قالب شهادات', 'fields_config' => []]);

    $this->certificate = Certificate::create([
        'student_id' => $this->owner->id,
        'section_id' => $section->id,
        'template_id' => $template->id,
        'serial_number' => 'CERT-TEST-1',
        'verification_token' => \Illuminate\Support\Str::uuid()->toString(),
        'issued_at' => now(),
    ]);
});

it('lets a student download their own certificate image', function () {
    $this->actingAs($this->owner, 'student')
        ->get(route('student.certificates.download', $this->certificate))
        ->assertOk();
});

it('blocks a student from downloading another students certificate', function () {
    $this->actingAs($this->outsider, 'student')
        ->get(route('student.certificates.download', $this->certificate))
        ->assertForbidden();
});
