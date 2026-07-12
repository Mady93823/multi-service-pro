<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M17. `users.is_active` has existed since M01 but nothing enforced it; the
 * admin Customers screen turns it into a real block switch, and the reason is
 * shown back to the admin (the activity log records who and when).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('blocked_reason')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('blocked_reason');
        });
    }
};
