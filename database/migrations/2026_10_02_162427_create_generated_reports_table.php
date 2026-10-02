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
        Schema::create('generated_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_type', 32);
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->boolean('include_entered_in_error')->default(false);
            $table->string('storage_disk');
            $table->string('storage_path')->unique();
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->json('metadata')->nullable();
            $table->foreignId('regenerated_from_id')->nullable()->constrained('generated_reports')->restrictOnDelete();
            $table->timestamps();
            $table->index(['report_type', 'generated_at']);
            $table->index(['client_id', 'generated_at']);
            $table->index(['site_id', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
