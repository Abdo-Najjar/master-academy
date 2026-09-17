<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The receipt behind a payment, kept in the media library rather than as a
 * loose path on the row.
 *
 * Every screen that takes money asks for the same thing — a photo of the
 * transfer, or the scanned voucher — so the field is declared once and the
 * rules (which types, how big, one per record) hold everywhere.
 *
 * Two shapes are needed because two kinds of screen exist. A resource form
 * edits a record that already exists, so the media component can write straight
 * through to it. A modal that *creates* the record has nothing to attach to
 * while it is open, so it holds the upload in memory and `attach()` moves it
 * across once the row is there.
 */
class ReceiptAttachment
{
    public const COLLECTION = 'receipt';

    /** 5 MB — a phone photo of a transfer screen, not a scanned archive. */
    public const MAX_SIZE_KB = 5120;

    /** @var list<string> */
    public const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /** The field for a form bound to the record that will own the receipt. */
    public static function field(string $name = self::COLLECTION): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($name)
            ->label(__('Payment Receipt'))
            ->helperText(__('Attach the transfer/notification receipt (optional).'))
            ->collection(self::COLLECTION)
            ->acceptedFileTypes(self::ACCEPTED)
            ->maxSize(self::MAX_SIZE_KB)
            ->downloadable()
            ->openable()
            ->columnSpanFull();
    }

    /**
     * The field for a modal that creates the record on submit.
     *
     * `storeFiles(false)` keeps the upload as a temporary file in the form
     * state instead of writing it somewhere of its own — otherwise the file
     * would be saved twice, once loose on the disk and once in the library.
     */
    public static function pendingField(string $name = self::COLLECTION): FileUpload
    {
        return FileUpload::make($name)
            ->label(__('Payment Receipt'))
            ->helperText(__('Attach the transfer/notification receipt (optional).'))
            ->acceptedFileTypes(self::ACCEPTED)
            ->maxSize(self::MAX_SIZE_KB)
            ->storeFiles(false)
            ->columnSpanFull();
    }

    /**
     * Move whatever `pendingField()` collected onto the record's receipt.
     *
     * Accepts the raw form state, which Filament hands back as an array keyed
     * by upload id even for a single file, and tolerates a bare path so a
     * caller (a test, an import) can attach a file it already has on disk.
     */
    public static function attach(Model&HasMedia $record, mixed $state): void
    {
        foreach (is_array($state) ? $state : [$state] as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $record->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection(self::COLLECTION);

                continue;
            }

            if (is_string($file) && $file !== '' && is_file($file)) {
                $record->addMedia($file)->toMediaCollection(self::COLLECTION);
            }
        }
    }

    /** The receipt to link to, or null when none was attached. */
    public static function url(Model&HasMedia $record): ?string
    {
        $media = $record->getFirstMedia(self::COLLECTION);

        return $media?->getUrl();
    }

    /**
     * Wallets are the awkward case: the movement they belong to is a vendor
     * model that cannot own media, so the receipt is filed against the student
     * or trainer whose wallet moved, and the movement remembers which one.
     */
    public const WALLET_COLLECTION = 'payment-receipts';

    /**
     * Put a wallet receipt on the account it belongs to.
     *
     * @return int|null the media id to record on the movement, if a file came
     */
    public static function attachToWallet(Model&HasMedia $payable, mixed $state): ?int
    {
        foreach (is_array($state) ? $state : [$state] as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                return $payable->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection(self::WALLET_COLLECTION)
                    ->getKey();
            }

            if (is_string($file) && $file !== '' && is_file($file)) {
                return $payable->addMedia($file)
                    ->toMediaCollection(self::WALLET_COLLECTION)
                    ->getKey();
            }
        }

        return null;
    }

    /**
     * The receipt behind a wallet movement, whichever way it was filed.
     *
     * Receipts taken before the library existed are a path in the movement's
     * own metadata; ones taken since are media. Both are still real receipts,
     * so both are still shown — nothing was moved, the reader simply looks in
     * the new place first and falls back to the old one.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function walletUrl(?array $meta): ?string
    {
        if ($mediaId = $meta['receipt_media_id'] ?? null) {
            return Media::query()->find($mediaId)?->getUrl();
        }

        $path = $meta['receipt_path'] ?? null;

        return is_string($path) && $path !== ''
            ? Storage::disk('public')->url($path)
            : null;
    }
}
