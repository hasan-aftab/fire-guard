<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('managed_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedBigInteger('record_id');
            $table->string('storage_disk');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('next_chunk')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('managed_uploads');
    }
};
