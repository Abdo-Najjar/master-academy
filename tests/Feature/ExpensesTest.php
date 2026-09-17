<?php

use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\PaymentType;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\ReceiptAttachment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Money going out. The centre could record every shekel a student handed over
 * and not a single one it paid out, so rent and bills lived on paper and the
 * reports showed revenue as if it were profit.
 */
function expenseAdmin(): User
{
    $user = User::create([
        'name' => 'Expense Admin',
        'email' => 'expense-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'expense-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

function expenseType(string $ar = 'إيجار مكان'): ExpenseType
{
    return ExpenseType::create(['name' => ['ar' => $ar, 'en' => 'Type '.uniqid()]]);
}

it('records an expense with its type, payment method and receipt', function () {
    $admin = expenseAdmin();
    $type = expenseType();
    $paymentType = PaymentType::create(['name' => ['ar' => 'نقداً', 'en' => 'Cash']]);

    Livewire::actingAs($admin)
        ->test(ManageExpenses::class)
        ->callAction('create', data: [
            'expense_type_id' => $type->id,
            'payment_type_id' => $paymentType->id,
            'amount' => 1500,
            'spent_at' => now()->toDateString(),
            'payee' => 'صاحب المبنى',
            'reference' => 'INV-77',
            'note' => 'إيجار شهر',
        ])
        ->assertHasNoActionErrors();

    $expense = Expense::query()->latest('id')->first();

    expect($expense)->not->toBeNull()
        ->and((float) $expense->amount)->toBe(1500.0)
        ->and($expense->expense_type_id)->toBe($type->id)
        ->and($expense->payment_type_id)->toBe($paymentType->id)
        ->and($expense->reference)->toBe('INV-77');
});

it('keeps the expense receipt in the media library, one per expense', function () {
    Storage::fake('public');

    $expense = Expense::create([
        'expense_type_id' => expenseType()->id,
        'amount' => 1200,
        'spent_at' => now()->toDateString(),
    ]);

    expect($expense->receiptUrl())->toBeNull();

    ReceiptAttachment::attach($expense, receiptFixture());

    expect($expense->refresh()->getMedia(ReceiptAttachment::COLLECTION))->toHaveCount(1)
        ->and($expense->receiptUrl())->not->toBeNull();

    // A re-upload replaces the voucher rather than leaving two to choose from.
    ReceiptAttachment::attach($expense, receiptFixture('corrected.jpg'));

    expect($expense->refresh()->getMedia(ReceiptAttachment::COLLECTION))->toHaveCount(1);
});

it('refuses an expense with no type or no amount', function () {
    $admin = expenseAdmin();

    Livewire::actingAs($admin)
        ->test(ManageExpenses::class)
        ->callAction('create', data: [
            'spent_at' => now()->toDateString(),
        ])
        ->assertHasActionErrors(['expense_type_id', 'amount']);

    expect(Expense::count())->toBe(0);
});

it('counts only expenses spent inside the reported window', function () {
    $admin = expenseAdmin();
    $type = expenseType();

    Expense::create([
        'expense_type_id' => $type->id,
        'amount' => 300,
        'spent_at' => now()->startOfMonth()->addDays(3)->toDateString(),
    ]);

    // Last month's rent is last month's problem.
    Expense::create([
        'expense_type_id' => $type->id,
        'amount' => 900,
        'spent_at' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
    ]);

    $page = Livewire::actingAs($admin)->test(Reports::class);

    expect($page->instance()->getExpenseStatsProperty()['total'])->toBe(300.0)
        ->and($page->instance()->getExpenseStatsProperty()['count'])->toBe(1);
});

it('breaks the period expenses down by type, biggest first', function () {
    $admin = expenseAdmin();
    $rent = expenseType('إيجار مكان');
    $bills = expenseType('فواتير');

    Expense::create(['expense_type_id' => $bills->id, 'amount' => 120, 'spent_at' => now()->toDateString()]);
    Expense::create(['expense_type_id' => $rent->id, 'amount' => 2000, 'spent_at' => now()->toDateString()]);
    Expense::create(['expense_type_id' => $rent->id, 'amount' => 500, 'spent_at' => now()->toDateString()]);

    $breakdown = Livewire::actingAs($admin)->test(Reports::class)
        ->instance()
        ->getExpenseBreakdownProperty();

    expect($breakdown)->toHaveCount(2)
        ->and($breakdown[0]['name'])->toBe('إيجار مكان')
        ->and($breakdown[0]['total'])->toBe(2500.0)
        ->and($breakdown[0]['count'])->toBe(2)
        ->and($breakdown[1]['total'])->toBe(120.0);
});

it('drops the expenses of a deleted type out of the reports', function () {
    $admin = expenseAdmin();
    $type = expenseType();

    Expense::create([
        'expense_type_id' => $type->id,
        'amount' => 400,
        'spent_at' => now()->toDateString(),
    ]);

    $type->delete();

    $stats = Livewire::actingAs($admin)->test(Reports::class)
        ->instance()
        ->getExpenseStatsProperty();

    expect($stats['total'])->toBe(0.0);
});

it('subtracts expenses from the net result on the reports page', function () {
    $admin = expenseAdmin();
    $type = expenseType();

    Expense::create([
        'expense_type_id' => $type->id,
        'amount' => 750,
        'spent_at' => now()->toDateString(),
    ]);

    // No revenue and no bookings, so the whole result is what went out.
    expect(Livewire::actingAs($admin)->test(Reports::class)->instance()->getNetResultProperty())
        ->toBe(-750.0);
});
