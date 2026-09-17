<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_booking_times', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_booking_id')->constrained()->cascadeOnDelete();
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->softDeletes();

            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_times');
    }
};
