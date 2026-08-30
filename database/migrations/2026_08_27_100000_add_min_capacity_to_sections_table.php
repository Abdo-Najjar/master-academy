<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `capacity` has always been the ceiling. A section also has a floor — the
 * number of students below which it is not worth running — and the desk had
 * nowhere to record it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->unsignedInteger('min_capacity')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn('min_capacity');
        });
    }
};
