<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance now points at the concrete session it belongs to, and records
     * who took it and who last changed it (admin user or trainer — hence the
     * polymorphic columns).
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->foreignId('section_session_id')->nullable()->after('section_id')
                ->constrained('section_sessions')->nullOnDelete();
            $table->nullableMorphs('recorded_by');
            $table->nullableMorphs('updated_by');
            $table->timestamp('recorded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('section_session_id');
            $table->dropMorphs('recorded_by');
            $table->dropMorphs('updated_by');
            $table->dropColumn('recorded_at');
        });
    }
};
