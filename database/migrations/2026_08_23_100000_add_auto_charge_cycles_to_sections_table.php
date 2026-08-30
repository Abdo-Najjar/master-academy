<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether completing a payment cycle charges the student by itself.
     *
     * Until now a per-session section counted its lessons and flipped the
     * student to "payment due" once the cycle was consumed, but the money only
     * moved when someone opened the collect-payment dialog — so a centre that
     * simply took attendance never saw a charge. On by default: that is what
     * "100 ₪ every 8 lessons" is understood to mean. A centre that collects
     * cash by hand can switch it off per section.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->boolean('auto_charge_cycles')->default(true)->after('cycle_fee');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn('auto_charge_cycles');
        });
    }
};
