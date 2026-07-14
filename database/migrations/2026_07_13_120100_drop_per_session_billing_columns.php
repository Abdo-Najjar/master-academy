<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move to a course-only (fixed fee) billing model: drop the per-session
     * fee type and session-tracking columns.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            foreach (['fee_type', 'sessions_per_fee_cycle'] as $column) {
                if (Schema::hasColumn('sections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('registrations', function (Blueprint $table): void {
            foreach (['session_offset', 'paid_through_session'] as $column) {
                if (Schema::hasColumn('registrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->string('fee_type')->default('fixed_course')->after('section_type');
            $table->unsignedInteger('sessions_per_fee_cycle')->nullable()->after('fee_type');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->unsignedInteger('session_offset')->default(0)->after('trainer_amount');
            $table->unsignedInteger('paid_through_session')->default(0)->after('session_offset');
        });
    }
};
