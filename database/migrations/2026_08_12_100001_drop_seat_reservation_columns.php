<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seat reservation was never wired to any billing logic — the fields sat on
     * the section form collecting values nothing ever read. Dropped at the
     * centre's request rather than left as a trap.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn(['seat_reservation_type', 'seat_reservation_amount']);
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn('seat_reservation_paid');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->string('seat_reservation_type')->nullable();
            $table->decimal('seat_reservation_amount', 10, 2)->nullable();
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->decimal('seat_reservation_paid', 10, 2)->default(0);
        });
    }
};
