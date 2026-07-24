<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('sections')->select('id', 'name')->get();

        Schema::table('sections', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
        });

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->name, true);

            $value = is_array($decoded)
                ? (string) ($decoded['ar'] ?? $decoded['en'] ?? reset($decoded) ?: '')
                : (string) $row->name;

            DB::table('sections')->where('id', $row->id)->update(['name' => $value]);
        }

        Schema::table('sections', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        $rows = DB::table('sections')->select('id', 'name')->get();

        Schema::table('sections', function (Blueprint $table): void {
            $table->json('name')->nullable()->change();
        });

        foreach ($rows as $row) {
            DB::table('sections')->where('id', $row->id)->update([
                'name' => json_encode(['ar' => (string) $row->name], JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('sections', function (Blueprint $table): void {
            $table->json('name')->nullable(false)->change();
        });
    }
};
