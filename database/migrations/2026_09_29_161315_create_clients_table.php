<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_phone', 24)->nullable();
            $table->boolean('whatsapp_reminders_enabled')->default(false);
            $table->string('email')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('entered_in_error_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
