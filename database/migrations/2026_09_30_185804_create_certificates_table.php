<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('certificate_type');
            $table->date('issue_date');
            $table->date('expiry_date')->index();
            $table->string('issued_by')->nullable();
            $table->string('status')->default('valid');
            $table->timestamp('entered_in_error_at')->nullable();
            $table->timestamps();
            $table->index('entered_in_error_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
