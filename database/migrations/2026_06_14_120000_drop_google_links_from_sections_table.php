<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            foreach (['google_meet_url', 'google_classroom_url'] as $column) {
                if (Schema::hasColumn('sections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            if (! Schema::hasColumn('sections', 'google_meet_url')) {
                $table->string('google_meet_url')->nullable();
            }
            if (! Schema::hasColumn('sections', 'google_classroom_url')) {
                $table->string('google_classroom_url')->nullable();
            }
        });
    }
};
