<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M18. A library asset is a reusable marketing image: one row, one file in the
 * medialibrary `library` collection on the PUBLIC disk.
 *
 * Consumers never share the row — picking an asset copies its file into the
 * consumer's own collection, tagged with `library_asset_id` (ADR D29), so a
 * banner's image cannot vanish because someone tidied the library.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
