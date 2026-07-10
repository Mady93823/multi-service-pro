<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-category commission override (M09). Null = inherit the parent
        // category's override, else the payments.commission_percent setting.
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('is_active');
        });

        // Created before `earnings` because an earning points at the payout
        // that settled it.
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('requested'); // requested|approved|paid|rejected
            $table->json('method_details'); // {method: upi|bank, ...} — provider supplied
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('reference')->nullable(); // bank UTR / transfer id
            $table->string('note')->nullable(); // rejection reason
            $table->timestamps();

            $table->index(['provider_id', 'status']);
            $table->index('status');
        });

        // Provider ledger, append-only: rows are never deleted and their money
        // columns are never rewritten. A correction is a compensating row
        // (type = reversal / adjustment), same rule as wallet_transactions.
        //
        // Invariant, asserted in tests:
        //     net = gross - commission - collected_amount
        //
        // `collected_amount` is what the provider already took at the door on a
        // cash job (the full customer total, tax included), so a cash job's net
        // is negative: the provider owes the platform its commission and the
        // GST they pocketed. Never clamp that to zero.
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users');
            $table->foreignId('booking_id')->constrained();
            $table->foreignId('payout_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('job'); // job|reversal|adjustment
            $table->decimal('gross', 12, 2); // pre-tax service value of the job
            $table->decimal('commission', 12, 2);
            $table->decimal('collected_amount', 12, 2)->default(0);
            $table->decimal('net', 12, 2); // signed — negative when the provider owes
            $table->decimal('commission_rate', 5, 2); // blended rate snapshot
            $table->string('status', 20)->default('pending'); // pending|available|paid_out
            $table->timestamp('available_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
            $table->index(['payout_request_id']);
            // Double-write backstop: one job row and at most one reversal row
            // per booking, so a re-fired completion listener cannot pay twice.
            $table->unique(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earnings');
        Schema::dropIfExists('payout_requests');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });
    }
};
