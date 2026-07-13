<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            // Set when the testimonial was promoted from a real review (M10) —
            // nullOnDelete: deleting the review must not delete the quote the
            // marketing page is built around.
            $table->foreignId('review_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sponsors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('popups', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            // Markdown, rendered through MarkdownRenderer — never raw HTML (D20).
            $table->text('body')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();
            $table->string('audience')->default('everyone');
            // Days before the popup is shown again to the same browser.
            $table->unsignedInteger('frequency_days')->default(7);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('source')->default('footer');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
        Schema::dropIfExists('popups');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('testimonials');
    }
};
