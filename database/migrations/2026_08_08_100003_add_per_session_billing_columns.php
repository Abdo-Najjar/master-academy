<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restores billing by number of sessions alongside the fixed-course model:
     * a section charges either one price for the whole course, or `cycle_fee`
     * every `sessions_per_cycle` sessions held.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            // fixed_course | per_sessions
            $table->string('fee_type')->default('fixed_course')->after('price');
            $table->unsignedInteger('sessions_per_cycle')->nullable()->after('fee_type');
            $table->decimal('cycle_fee', 10, 2)->nullable()->after('sessions_per_cycle');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            // Sessions the section had already held when the student joined —
            // counting starts from the next one.
            $table->unsignedInteger('session_offset')->default(0)->after('financial_status');
            // Sessions charged to this student so far (absence does not matter).
            $table->unsignedInteger('sessions_counted')->default(0)->after('session_offset');
            // How far ahead the student has paid, in sessions.
            $table->unsignedInteger('paid_through_session')->default(0)->after('sessions_counted');
            // Counting pauses while the student is suspended.
            $table->timestamp('paused_at')->nullable()->after('paid_through_session');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn(['fee_type', 'sessions_per_cycle', 'cycle_fee']);
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn(['session_offset', 'sessions_counted', 'paid_through_session', 'paused_at']);
        });
    }
};
