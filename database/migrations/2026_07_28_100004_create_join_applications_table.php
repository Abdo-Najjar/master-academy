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
        Schema::create('join_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('full_name');
            $table->string('phone');
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('gender')->nullable();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('program_name')->nullable()->comment('Free-text program when the visitor picked "other"');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('contact_preference', ['whatsapp', 'phone'])->default('whatsapp');
            $table->text('notes')->nullable();
            $table->string('source')->default('site-join-form');
            $table->enum('status', ['new', 'contacted', 'enrolled', 'rejected'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('join_applications');
    }
};
