<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // 'absence' | 'unpaid_attendance'
            $table->unsignedInteger('threshold_value');
            $table->json('payload')->nullable();
            $table->timestamp('notified_at')->useCurrent();
            $table->timestamps();

            $table->unique(['student_id', 'section_id', 'kind', 'threshold_value'], 'student_alerts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_alerts');
    }
};
