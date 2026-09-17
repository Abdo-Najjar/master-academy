<?php

use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Support\ReceiptAttachment;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Facades\Storage;

/**
 * Receipts on wallet movements, filed two different ways.
 *
 * Everything taken before the media library went in is a plain path in the
 * movement's own metadata. Everything taken since is media hanging off the
 * account whose balance moved. Nothing was migrated and nothing needs to be:
 * the reader looks in the new place first and falls back to the old one, so a
 * receipt from either era still opens from the same button.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->student = Student::create([
        'name' => ['ar' => 'ليان', 'en' => 'Layan'],
        'username' => 'receipt_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->section = Section::create([
        'name' => 'شعبة الوصولات',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'price' => 400,
    ]);
});

it('opens a receipt that was filed the old way, as a path in the metadata', function () {
    Storage::disk('public')->put('payment-receipts/legacy.jpg', 'x');

    $this->student->depositFloat(100, [
        'description' => 'دفعة قديمة',
        'receipt_path' => 'payment-receipts/legacy.jpg',
    ]);

    $meta = Transaction::query()->latest('id')->first()->meta;

    expect(ReceiptAttachment::walletUrl($meta))
        ->toBe(Storage::disk('public')->url('payment-receipts/legacy.jpg'));
});

it('files a new receipt in the media library and opens that instead', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => now()->subWeek()->toDateString(),
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    CollectPaymentAction::collect($registration, [
        'amount' => 250,
        'payment_type_id' => PaymentType::create(['name' => ['ar' => 'نقداً', 'en' => 'Cash']])->id,
        'receipt' => receiptFixture('new-receipt.jpg'),
    ]);

    $meta = Transaction::query()->where('type', 'deposit')->latest('id')->first()->meta;

    expect($meta['receipt_media_id'] ?? null)->not->toBeNull()
        ->and($meta)->not->toHaveKey('receipt_path')
        ->and(ReceiptAttachment::walletUrl($meta))->not->toBeNull()
        // Filed against the account whose balance moved, since the movement
        // itself is a vendor model and cannot own media.
        ->and($this->student->fresh()->getMedia(ReceiptAttachment::WALLET_COLLECTION))->toHaveCount(1);
});

it('says there is no receipt when neither place holds one', function () {
    $this->student->depositFloat(100, ['description' => 'بدون وصل']);

    expect(ReceiptAttachment::walletUrl(Transaction::query()->latest('id')->first()->meta))->toBeNull()
        ->and(ReceiptAttachment::walletUrl(null))->toBeNull()
        ->and(ReceiptAttachment::walletUrl(['receipt_path' => '']))->toBeNull();
});

it('keeps each payment\'s own voucher rather than replacing the last one', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => now()->subWeek()->toDateString(),
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    CollectPaymentAction::collect($registration, ['amount' => 100, 'receipt' => receiptFixture('first.jpg')]);
    CollectPaymentAction::collect($registration, ['amount' => 100, 'receipt' => receiptFixture('second.jpg')]);

    $urls = Transaction::query()
        ->where('type', 'deposit')
        ->get()
        ->map(fn (Transaction $t): ?string => ReceiptAttachment::walletUrl($t->meta))
        ->filter()
        ->unique();

    // Two payments, two receipts, two different files — a wallet is a running
    // account, not a single voucher.
    expect($this->student->fresh()->getMedia(ReceiptAttachment::WALLET_COLLECTION))->toHaveCount(2)
        ->and($urls)->toHaveCount(2);
});
