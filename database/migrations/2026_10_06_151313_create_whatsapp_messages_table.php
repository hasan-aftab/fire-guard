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
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reminder_delivery_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->restrictOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->restrictOnDelete();
            $table->boolean('is_test')->default(false);
            $table->string('recipient', 24);
            $table->string('sender_display_phone_number', 24)->nullable();
            $table->string('phone_number_id', 32)->nullable();
            $table->string('waba_id', 32)->nullable();
            $table->string('template_name')->nullable();
            $table->string('template_language', 20)->nullable();
            $table->json('test_values')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('meta_message_id')->nullable()->unique();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->index(['is_test', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['whatsapp_account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
