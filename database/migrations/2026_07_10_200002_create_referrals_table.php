<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lazily generated share code (EnsureReferralCode) — nullable so
            // existing accounts don't need a backfill.
            $table->string('referral_code', 12)->nullable()->unique()->after('phone');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            // A user can be referred once, ever — the unique index is the contract.
            $table->foreignId('referee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code_used', 12);
            // Snapshot of what was actually credited, written at reward time —
            // stays null while pending so a settings change never lies here.
            $table->decimal('reward_amount', 10, 2)->nullable();
            $table->string('status', 10)->default('pending'); // pending|rewarded
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
