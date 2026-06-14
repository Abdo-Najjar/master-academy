<?php

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\CertificateService;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name'     => ['en' => 'Cert Trainer', 'ar' => 'مدرب'],
        'username' => 'cert_trainer_' . uniqid(),
        'password' => 'password',
    ]);

    $this->subject = Subject::create(['name' => ['en' => 'Arabic', 'ar' => 'اللغة العربية']]);

    $this->section = Section::create([
        'name'         => ['en' => 'Arabic Section', 'ar' => 'قسم اللغة العربية'],
        'subject_id'   => $this->subject->id,
        'trainer_id'   => $this->trainer->id,
        'price'        => 100,
        'trainer_rate' => 40,
    ]);

    $this->student = Student::create([
        'name'     => ['en' => 'Cert Student', 'ar' => 'طالب الشهادة'],
        'username' => 'cert_stu_' . uniqid(),
        'password' => 'password',
    ]);

    $this->template = CertificateTemplate::create([
        'name'         => 'Basic Template',
        'is_active'    => true,
        'canvas_width' => 800,
        'canvas_height' => 600,
        'fields_config' => [
            ['key' => 'student_name', 'x' => 100, 'y' => 200, 'font_size' => 24],
        ],
    ]);
});

it('issues a certificate with auto serial number and UUID token', function () {
    $cert = CertificateService::issue($this->student, $this->template, $this->section);

    expect($cert)->toBeInstanceOf(Certificate::class);
    expect($cert->student_id)->toBe($this->student->id);
    expect($cert->section_id)->toBe($this->section->id);
    expect($cert->template_id)->toBe($this->template->id);
    expect($cert->serial_number)->toMatch('/^CERT-\d{4}-\d{4}$/');
    expect($cert->verification_token)->toHaveLength(36); // UUID
});

it('issues a certificate without a section', function () {
    $cert = CertificateService::issue($this->student, $this->template, null);

    expect($cert->section_id)->toBeNull();
    expect($cert->serial_number)->toStartWith('CERT-');
});

it('serial numbers increment within same year', function () {
    $c1 = CertificateService::issue($this->student, $this->template);
    $c2 = CertificateService::issue($this->student, $this->template);

    [$year1, $seq1] = explode('-', substr($c1->serial_number, 5));
    [$year2, $seq2] = explode('-', substr($c2->serial_number, 5));

    expect($year1)->toBe($year2);
    expect((int) $seq2)->toBeGreaterThan((int) $seq1);
});

it('each certificate has a unique verification token', function () {
    $c1 = CertificateService::issue($this->student, $this->template);
    $c2 = CertificateService::issue($this->student, $this->template);

    expect($c1->verification_token)->not->toBe($c2->verification_token);
});

it('certificate can be found by verification token', function () {
    $cert = CertificateService::issue($this->student, $this->template);

    $found = Certificate::where('verification_token', $cert->verification_token)->first();

    expect($found?->id)->toBe($cert->id);
});
