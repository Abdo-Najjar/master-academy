<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at')->useCurrent();

            $table->unique(['announcement_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
    }
};
