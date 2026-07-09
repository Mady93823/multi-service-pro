<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 10)->default('active');
            // Last-known checkpoint — served to the polling fallback so a
            // reconnecting customer sees the provider without waiting for the
            // next ping (05-Live-Tracking).
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->decimal('last_accuracy_m', 6, 2)->nullable();
            $table->decimal('last_heading', 6, 2)->nullable();
            $table->decimal('last_speed_kmh', 6, 2)->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('tracking_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy_m', 6, 2)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            // Trail queries + the 30-day prune both scan by (session, time).
            $table->index(['tracking_session_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_points');
        Schema::dropIfExists('tracking_sessions');
    }
};
