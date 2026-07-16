<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('student_group_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unique(['student_group_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_student');
        Schema::dropIfExists('student_groups');
    }
};
