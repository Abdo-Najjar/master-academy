<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The centre has no guardian concept at all — no account, no portal, and no
     * contact kept on file. The three guardian columns are dropped; `school`,
     * `grade_level` and `enrolled_at` from the same original migration stay.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['parent_name', 'parent_phone', 'parent_whatsapp']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('parent_name')->nullable()->after('grade_level');
            $table->string('parent_phone')->nullable()->after('parent_name');
            $table->string('parent_whatsapp')->nullable()->after('parent_phone');
        });
    }
};
