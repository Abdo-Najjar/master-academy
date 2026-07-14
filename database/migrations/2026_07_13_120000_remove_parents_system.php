<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the parent-guardian subsystem: remove the students.parent_id link
     * and the parents table entirely.
     */
    public function up(): void
    {
        if (Schema::hasColumn('students', 'parent_id')) {
            Schema::table('students', function (Blueprint $table): void {
                // Drop the FK constraint before the column so the table rebuild
                // (SQLite) or ALTER (MySQL) doesn't reference a missing column.
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }

        Schema::dropIfExists('parents');
    }

    public function down(): void
    {
        Schema::create('parents', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('whatsapp')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('withdrawal_date')->constrained('parents')->nullOnDelete();
        });
    }
};
