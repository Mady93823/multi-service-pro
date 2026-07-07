<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('city', 120);
            // GeoJSON Polygon geometry; point-in-polygon runs in PHP so the
            // schema works identically on MySQL, MariaDB and sqlite (ADR D12).
            $table->json('geojson');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['city', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
