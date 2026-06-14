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
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // JSON array of field configs: [{key, label, x, y, font_size, font_color, font_family, font_weight, text_align, width}]
            $table->json('fields_config')->nullable();
            // Original canvas dimensions used when placing fields
            $table->unsignedInteger('canvas_width')->default(1000);
            $table->unsignedInteger('canvas_height')->default(700);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
