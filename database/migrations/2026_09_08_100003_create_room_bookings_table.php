<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            // Same shape as a section: a range of days plus the weekly slots
            // inside it, so a one-off booking is simply a range of one day.
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('status')->default('confirmed');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
    }
};
