<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('inspection_date');
            $table->string('result');
            $table->text('notes')->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->timestamp('entered_in_error_at')->nullable();
            $table->timestamps();
            $table->index('entered_in_error_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
