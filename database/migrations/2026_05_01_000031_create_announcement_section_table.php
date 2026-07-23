<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_section', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_section');
    }
};
