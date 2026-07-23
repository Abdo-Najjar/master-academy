<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exemption_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('funded_amount', 12, 2)->default(0);
            $table->decimal('exemption_amount', 10, 2)->default(0);
            $table->decimal('trainer_amount', 10, 2)->default(0);
            $table->decimal('trainer_credited_amount', 12, 2)->default(0);
            $table->decimal('seat_reservation_paid', 10, 2)->default(0);
            $table->string('financial_status')->default('ok');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'section_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
