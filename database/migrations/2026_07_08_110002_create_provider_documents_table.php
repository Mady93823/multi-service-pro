<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('file_path');
            $table->string('status', 20)->default('pending');
            $table->string('reject_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            // One live document per type; re-uploads replace the row.
            $table->unique(['provider_profile_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_documents');
    }
};
