<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where an offline customer sends the money (M22). Admin-managed rather
        // than a free-text blob in settings, so the payment row can point at the
        // exact account it was paid into and reconciliation has something to join.
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('account_name')->nullable();
            $table->string('account_number', 34)->nullable();
            $table->string('ifsc', 20)->nullable();
            $table->string('upi_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Where the platform sends a provider's payout (M22). Replaces the
        // free-text UPI/bank block typed into M09's payout dialog.
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10); // upi|bank
            $table->string('label')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number', 34)->nullable();
            $table->string('ifsc', 20)->nullable();
            $table->string('upi_id')->nullable();
            $table->boolean('is_default')->default(false);
            // Admin-verified: the payout screen shows whether anyone checked
            // these details before money left the building.
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'is_default']);
        });

        Schema::table('payments', function (Blueprint $table) {
            // Offline payments (D27): the row is an ordinary payments row with
            // gateway = offline, waiting in `initiated` for an admin to verify it
            // through the same ConfirmPayment every webhook calls.
            $table->foreignId('bank_account_id')->nullable()->after('gateway_ref')->constrained()->nullOnDelete();
            $table->string('reference')->nullable()->after('bank_account_id'); // UTR / txn id the customer quotes
            $table->foreignId('reviewed_by')->nullable()->after('captured_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('failure_reason')->nullable()->after('reviewed_at');
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            // method_details stays the snapshot (money columns are snapshots) —
            // this only records which stored account the snapshot came from.
            $table->foreignId('payout_account_id')->nullable()->after('provider_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_account_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reference', 'reviewed_at', 'failure_reason']);
        });

        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('bank_accounts');
    }
};
