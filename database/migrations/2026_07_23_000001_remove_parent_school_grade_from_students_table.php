<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['parent_name', 'parent_phone', 'parent_whatsapp', 'school', 'grade_level']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('parent_name')->nullable();
            $table->string('parent_phone')->nullable();
            $table->string('parent_whatsapp')->nullable();
            $table->string('school')->nullable();
            $table->string('grade_level')->nullable();
        });
    }
};
