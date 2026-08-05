<?php

use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
use App\Models\Student;
use App\Models\User;
use Bavix\Wallet\Models\Transaction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    $this->admin = User::firstOrCreate(
        ['email' => 'wallet-date-admin@ma.test'],
        ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()]
    );

    $this->student = Student::create([
        'name' => ['en' => 'Dated Student', 'ar' => 'طالب بتاريخ'],
        'username' => 'dated_'.uniqid(),
        'password' => 'password',
    ]);
});

it('back-dates a deposit to the date the operator entered', function () {
    Livewire::actingAs($this->admin)
        ->test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->callAction('deposit', [
            'amount' => 120,
            'transaction_date' => '2026-07-15 10:30',
        ])
        ->assertHasNoActionErrors();

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction->created_at->format('Y-m-d H:i'))->toBe('2026-07-15 10:30')
        ->and($transaction->meta['transaction_date'])->toBe('2026-07-15 10:30')
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(120.0);
});

it('keeps the current time when the date field is left at its default', function () {
    Livewire::actingAs($this->admin)
        ->test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->callAction('deposit', ['amount' => 50])
        ->assertHasNoActionErrors();

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction->created_at->isToday())->toBeTrue();
});
