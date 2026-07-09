<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per payment attempt/settlement (M08). Money-bearing → FK
        // restricts deletes (04-Database-Schema integrity rules).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->string('gateway', 20); // razorpay|stripe|cash|wallet
            $table->string('gateway_ref')->nullable(); // order/session/payment id
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('status', 20)->default('initiated');
            $table->json('payload')->nullable(); // raw gateway response snapshots
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            // Webhook idempotency backstop: one row per gateway reference.
            $table->unique(['gateway', 'gateway_ref']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Cached figure only — the ledger below is the source of truth.
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
        });

        // Append-only ledger: never update or delete rows; corrections are
        // compensating `adjustment` entries (04-Database-Schema).
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->string('type', 20); // topup|payment|refund|payout|adjustment
            $table->string('direction', 6); // credit|debit
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('payments');
    }
};
