<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            // Fee type: per_session (every N sessions) or fixed_course (flat fee)
            $table->string('fee_type')->default('per_session')->after('section_type');
            $table->unsignedInteger('sessions_per_fee_cycle')->nullable()->after('fee_type');
            // Seat reservation
            $table->string('seat_reservation_type')->nullable()->after('sessions_per_fee_cycle'); // fixed / percentage
            $table->decimal('seat_reservation_amount', 10, 2)->nullable()->after('seat_reservation_type');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn(['fee_type', 'sessions_per_fee_cycle', 'seat_reservation_type', 'seat_reservation_amount']);
        });
    }
};
