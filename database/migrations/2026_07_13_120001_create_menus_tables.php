<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            // One menu per location — the storefront renders a location, so a
            // second menu claiming the same one would be unreachable content.
            $table->string('location')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('type');
            // Route name, page slug, or URL — whichever the type says.
            $table->string('target')->nullable();
            $table->string('visibility')->default('everyone');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
