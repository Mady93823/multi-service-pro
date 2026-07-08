<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispatch offers (M06). One row per (booking, provider) offer; the state
 * machine still owns the booking status — this table only tracks who was
 * offered a job, under which strategy, and how they responded. `round` groups
 * the offers a single re-dispatch cycle produced so a timeout can expire just
 * that batch; `distance_km` snapshots the Haversine distance for admin insight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->string('strategy', 20);            // nearest | broadcast | manual
            $table->string('status', 20)->default('offered'); // offered | accepted | declined | expired
            $table->unsignedTinyInteger('round')->default(1);
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'provider_id']);
            $table->index(['booking_id', 'status']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_offers');
    }
};
