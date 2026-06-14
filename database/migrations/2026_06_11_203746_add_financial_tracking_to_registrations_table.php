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
        Schema::table('registrations', function (Blueprint $table): void {
            // How many section sessions had already occurred when student registered
            $table->unsignedInteger('session_offset')->default(0)->after('trainer_amount');
            // Absolute session number through which student has paid
            $table->unsignedInteger('paid_through_session')->default(0)->after('session_offset');
            // Financial due status
            $table->string('financial_status')->default('ok')->after('paid_through_session');
            // Seat reservation
            $table->decimal('seat_reservation_paid', 10, 2)->default(0)->after('financial_status');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn(['session_offset', 'paid_through_session', 'financial_status', 'seat_reservation_paid']);
        });
    }
};
