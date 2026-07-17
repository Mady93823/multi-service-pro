<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable in the schema — bookings placed before this column
            // existed have no number; the request layer makes it mandatory
            // for every new booking. Snapshots, like the address: a later
            // profile edit must not rewrite who to call for an old job.
            $table->string('contact_phone', 20)->nullable()->after('address_snapshot');
            $table->string('contact_phone_alt', 20)->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['contact_phone', 'contact_phone_alt']);
        });
    }
};
