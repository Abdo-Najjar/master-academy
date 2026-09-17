<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_booking_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_booking_id')->constrained()->cascadeOnDelete();
            // A booking is rarely settled in one go: the hall is held on a
            // deposit and the rest arrives later, so every instalment carries
            // its own payment method, date and receipt.
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at');
            // The receipt itself is a media-library attachment, not a column.
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_payments');
    }
};
