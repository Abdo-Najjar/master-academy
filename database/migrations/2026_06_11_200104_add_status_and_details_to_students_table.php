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
        Schema::table('students', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('is_active');
            $table->string('gender')->nullable()->after('dob');
            $table->string('school')->nullable()->after('gender');
            $table->string('grade_level')->nullable()->after('school');
            $table->string('withdrawal_reason')->nullable()->after('parent_whatsapp');
            $table->date('withdrawal_date')->nullable()->after('withdrawal_reason');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['status', 'gender', 'school', 'grade_level', 'withdrawal_reason', 'withdrawal_date']);
        });
    }
};
