<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M24 SEO: per-record overrides of the site-wide meta defaults.
 *
 * Blog posts already carry these (M21, added for exactly this milestone). Pages
 * and services get them now, so every public URL has one place to say what a
 * search engine and a shared link should show. Null means "use the defaults" —
 * an empty field is never an empty <title>.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('body');
            $table->string('meta_description', 300)->nullable()->after('meta_title');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description', 300)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
