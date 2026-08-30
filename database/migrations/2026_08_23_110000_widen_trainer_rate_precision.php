<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Four decimal places on every trainer-rate column, so the fractions a
     * centre actually quotes survive the round trip.
     *
     * At two places a third is stored as 33.33, which pays 99.99 ₪ of a 300 ₪
     * fee — a missing piaster the trainer notices, and one that compounds over
     * a term. 33.3333 pays 100.00 ₪.
     */
    public function up(): void
    {
        Schema::table('trainers', function (Blueprint $table): void {
            $table->decimal('default_rate', 7, 4)->default(0)->change();
        });

        Schema::table('sections', function (Blueprint $table): void {
            $table->decimal('trainer_rate', 7, 4)->nullable()->change();
        });

        Schema::table('section_sessions', function (Blueprint $table): void {
            $table->decimal('trainer_rate', 7, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table): void {
            $table->decimal('default_rate', 5, 2)->default(0)->change();
        });

        Schema::table('sections', function (Blueprint $table): void {
            $table->decimal('trainer_rate', 5, 2)->nullable()->change();
        });

        Schema::table('section_sessions', function (Blueprint $table): void {
            $table->decimal('trainer_rate', 5, 2)->nullable()->change();
        });
    }
};
