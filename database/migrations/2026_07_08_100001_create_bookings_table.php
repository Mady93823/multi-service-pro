<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->restrictOnDelete();
            // Address row may be deleted by the customer later; the snapshot
            // keeps the service location on the booking forever.
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('address_snapshot');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('slot_end_at');
            $table->string('status', 30);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('addon_total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->unsignedBigInteger('coupon_id')->nullable(); // FK added with M12 coupons
            $table->decimal('tax', 12, 2)->default(0);
            $table->json('tax_breakup')->nullable();
            $table->decimal('total', 12, 2);
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('payment_method', 20);
            $table->decimal('commission_rate_snapshot', 5, 2)->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->decimal('provider_earning', 12, 2)->nullable();
            $table->string('job_otp_code', 4)->nullable();
            $table->decimal('cancellation_fee', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index(['provider_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
