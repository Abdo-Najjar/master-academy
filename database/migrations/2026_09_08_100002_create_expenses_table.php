<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // Money that left the centre is recorded the same way money that
            // came in is: an amount, how it was paid, and the receipt for it.
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('spent_at');
            $table->string('payee')->nullable();
            $table->string('reference')->nullable();
            // The receipt itself is a media-library attachment, not a column.
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('spent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
