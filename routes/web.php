<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\PdfController;
use App\Livewire\Portal;
use App\Livewire\StudentAssignmentSubmission;
use App\Livewire\StudentDashboard;
use App\Livewire\StudentLogin;
use App\Livewire\TrainerAssignmentSubmissions;
use App\Livewire\TrainerDashboard;
use App\Livewire\TrainerLogin;
use Illuminate\Support\Facades\Route;

Route::get('/', Portal::class)->name('portal');

Route::get('/trainer/login', TrainerLogin::class)->name('trainer.login');
Route::get('/trainer/dashboard', TrainerDashboard::class)->name('trainer.dashboard')->middleware('trainer.auth');
Route::get('/trainer/assignments/{assignment}', TrainerAssignmentSubmissions::class)->name('trainer.assignments.show')->middleware('trainer.auth');

Route::get('/student/login', StudentLogin::class)->name('student.login');
Route::get('/student/dashboard', StudentDashboard::class)->name('student.dashboard')->middleware('student.auth');
Route::get('/student/assignments/{assignment}', StudentAssignmentSubmission::class)->name('student.assignments.show')->middleware('student.auth');
Route::get('/student/certificates/{certificate}/download', [PdfController::class, 'studentCertificateImage'])->name('student.certificates.download')->middleware('student.auth');

Route::get('/certificates/verify/{token}', function (string $token) {
    $cert = \App\Models\Certificate::where('verification_token', $token)->with(['student', 'section.subject', 'template'])->firstOrFail();
    return view('certificates.verify', ['certificate' => $cert]);
})->name('certificates.verify');

// System backup download / delete (protected by Filament admin auth + permission gate inside controller)
Route::middleware(['web', 'auth'])
    ->prefix('admin/backup')
    ->name('admin.backup.')
    ->group(function () {
        Route::get('download/{filename}', [BackupController::class, 'download'])
            ->where('filename', '[A-Za-z0-9._\- ]+\.zip')
            ->name('download');

        Route::delete('{filename}', [BackupController::class, 'destroy'])
            ->where('filename', '[A-Za-z0-9._\- ]+\.zip')
            ->name('destroy');
    });

// PDF downloads (gated inside controller via hexa)
Route::middleware(['web', 'auth'])
    ->prefix('admin/pdf')
    ->name('admin.pdf.')
    ->group(function () {
        Route::get('receipt/{registration}', [PdfController::class, 'receipt'])->name('receipt');
        Route::get('student-card/{student}', [PdfController::class, 'studentCard'])->name('student-card');
        Route::get('certificate-image/{certificate}', [PdfController::class, 'certificateImage'])->name('certificate-image');
        Route::get('attendance-sheet/{section}', [PdfController::class, 'attendanceSheet'])->name('attendance-sheet');
    });
