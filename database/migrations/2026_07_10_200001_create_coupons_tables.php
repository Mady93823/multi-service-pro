<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('type', 10); // flat|percent
            $table->decimal('value', 10, 2);
            // Percent coupons only: ceiling on the computed discount.
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order', 10, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Audit trail: one row per redemption, written at placement (ADR D18).
        // Restrict delete — a used coupon is deactivated, never removed.
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // One redemption per booking — the unique index is the contract.
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('discount_applied', 10, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['coupon_id', 'user_id']);
        });

        // bookings.coupon_id existed since M04 as a bare column; the FK
        // lands now that the coupons table exists.
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
        });
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
