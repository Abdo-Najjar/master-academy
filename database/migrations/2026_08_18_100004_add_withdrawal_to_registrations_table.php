<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day a student left a section for good, as opposed to taking a break.
     *
     * A pause says "they will be back, do not charge them meanwhile" and keeps
     * them on the attendance sheet. Leaving is the other thing the paper
     * registers show — a row that simply ends. From `left_at` on, the student
     * is off the sheet, off the bill, and their seat is free again, while
     * everything they attended before it stays on the record.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->date('left_at')->nullable()->after('enrolled_at');
            $table->string('leave_reason')->nullable()->after('left_at');
            // section | centre — who ended it. Leaving the centre closes every
            // section the student was in, and coming back should re-open only
            // those, not one they had separately dropped on their own.
            $table->string('leave_source')->nullable()->after('leave_reason');

            $table->index(['section_id', 'left_at']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex(['section_id', 'left_at']);
            $table->dropColumn(['left_at', 'leave_reason', 'leave_source']);
        });
    }
};
