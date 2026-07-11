<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('slug', 150)->unique();
            // Markdown source; rendered HTML is never stored — the sanitizing
            // renderer stays the single output path (ADR D20).
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'show_in_footer', 'sort_order']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            // BCP-47-ish code, strictly validated ("hi", "pt_BR") — it becomes
            // part of the lang/{code}.json filename, so the pattern doubles as
            // the path-traversal guard.
            $table->string('code', 12)->unique();
            $table->string('name', 50);
            $table->string('native_name', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('pages');
    }
};
