<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M23 communications (ADR D25).
 *
 * All three tables are *optional layers* over behaviour that already works:
 * `email_templates` overrides a notification's shipped default mail, and a row
 * that is missing, disabled or broken simply falls back to it;
 * `notification_preferences` turns channels off, never on beyond what the
 * platform ships; `sms_logs` is an audit trail, never a queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            // One row per notification event (App\Domain\Comms\NotificationEvent).
            $table->string('event_key', 64)->unique();
            $table->string('subject', 191);
            $table->text('body'); // markdown source, rendered through MarkdownRenderer (D20)
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            // Null user = the platform default row an admin edits on the matrix.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->string('channel', 20);
            $table->boolean('is_enabled');
            $table->timestamps();

            $table->unique(['user_id', 'event_key', 'channel']);
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 32);
            $table->string('event_key', 64);
            $table->text('body');
            $table->string('gateway', 20);
            $table->string('status', 10); // sent | failed
            $table->json('response')->nullable();
            // Append-only, like every other ledger here: no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('email_templates');
    }
};
