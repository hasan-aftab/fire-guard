<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->string('serial_number')->nullable();
            $table->text('location_desc')->nullable();
            $table->date('install_date')->nullable();
            $table->date('last_service_date')->nullable();
            $table->date('next_service_due')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['status', 'next_service_due']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
