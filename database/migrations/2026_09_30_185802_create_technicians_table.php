<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('entered_in_error_at')->nullable();
            $table->timestamps();
            $table->index('license_expiry');
            $table->index(['full_name', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technicians');
    }
};
