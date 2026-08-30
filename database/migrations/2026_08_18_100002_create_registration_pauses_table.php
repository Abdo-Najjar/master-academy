<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every stretch of time a registration was not being counted.
     *
     * `registrations.paused_at` on its own only says "not counting right now",
     * which was enough while the counter was incremented lesson by lesson. Now
     * that the counter is recomputed from the lessons themselves, a resumed
     * registration would silently pick up everything it skipped unless the
     * skipped window is written down — hence a row per pause, with dates so a
     * break that happened two months ago can be recorded after the fact.
     */
    public function up(): void
    {
        Schema::create('registration_pauses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->date('paused_from');
            // Null while the pause is still open.
            $table->date('resumed_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'paused_from']);
        });

        // Registrations already paused keep their break, starting from the day
        // they were paused.
        $open = DB::table('registrations')
            ->whereNotNull('paused_at')
            ->whereNull('deleted_at')
            ->get(['id', 'paused_at']);

        foreach ($open as $registration) {
            DB::table('registration_pauses')->insert([
                'registration_id' => $registration->id,
                'paused_from' => substr((string) $registration->paused_at, 0, 10),
                'resumed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_pauses');
    }
};
