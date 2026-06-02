<?php

use App\Models\Student;

beforeEach(function () {
    $this->student = Student::create([
        'name' => ['en' => 'Wallet Student', 'ar' => 'طالب محفظة'],
        'username' => 'wallet_'.uniqid(),
        'password' => 'password',
    ]);
});

it('starts with zero balance', function () {
    expect((float) $this->student->balanceFloat)->toBe(0.0);
});

it('increases balance after deposit', function () {
    $this->student->depositFloat(100.50, ['description' => 'test deposit']);
    $this->student->refresh();

    expect((float) $this->student->balanceFloat)->toBe(100.50);
});

it('decreases balance after withdraw', function () {
    $this->student->depositFloat(200, ['description' => 'seed']);
    $this->student->refresh();

    $this->student->forceWithdrawFloat(50, ['description' => 'test withdraw']);
    $this->student->refresh();

    expect((float) $this->student->balanceFloat)->toBe(150.0);
});

it('allows negative balance via forceWithdrawFloat', function () {
    $this->student->forceWithdrawFloat(75, ['description' => 'overdraft']);
    $this->student->refresh();

    expect((float) $this->student->balanceFloat)->toBe(-75.0);
});

it('persists transaction metadata', function () {
    $this->student->depositFloat(40, [
        'description' => 'Test deposit',
        'note' => 'Cash payment',
        'payment_type_id' => null,
    ]);

    $tx = \Bavix\Wallet\Models\Transaction::where('wallet_id', $this->student->wallet->id)->latest('id')->first();

    expect($tx->meta['description'])->toBe('Test deposit');
    expect($tx->meta['note'])->toBe('Cash payment');
});
