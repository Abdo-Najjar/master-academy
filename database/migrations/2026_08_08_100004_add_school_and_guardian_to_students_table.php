<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guardian contact details live on the student as plain fields (there is no
     * guardian account or portal in this system) so absence/payment alerts can
     * reach the guardian directly.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('school')->nullable()->after('gender');
            $table->string('grade_level')->nullable()->after('school');
            $table->string('parent_name')->nullable()->after('grade_level');
            $table->string('parent_phone')->nullable()->after('parent_name');
            $table->string('parent_whatsapp')->nullable()->after('parent_phone');
            $table->date('enrolled_at')->nullable()->after('parent_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['school', 'grade_level', 'parent_name', 'parent_phone', 'parent_whatsapp', 'enrolled_at']);
        });
    }
};
