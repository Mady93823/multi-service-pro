<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public contact form opens a support ticket rather than filling a second
 * inbox (M19) — and a visitor who has not signed up still has to be able to ask
 * a question. So a ticket may have no owner, and carries the name and email the
 * guest typed instead. A guest ticket is admin-only: there is no account it
 * could belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn(['guest_name', 'guest_email']);
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
