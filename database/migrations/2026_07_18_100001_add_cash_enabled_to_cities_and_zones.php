<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether pay-after-service (cash) is offered in a geography (D43). Both
     * levels default to true so an existing install keeps its behaviour; cash
     * is offered only where city AND zone allow it — the city switch closes a
     * whole town in one click, the zone switch handles a single area.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->boolean('cash_enabled')->default(true)->after('is_active');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('cash_enabled')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('cash_enabled');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('cash_enabled');
        });
    }
};
