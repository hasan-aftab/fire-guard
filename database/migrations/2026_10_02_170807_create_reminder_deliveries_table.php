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
        Schema::create('reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 20)->default('email');
            $table->string('reminder_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('audience', 20)->default('client');
            $table->string('recipient')->default('');
            $table->string('recipient_name')->nullable();
            $table->string('client_name');
            $table->string('client_contact_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->string('site_name');
            $table->text('site_address')->nullable();
            $table->string('subject_name');
            $table->date('due_date');
            $table->smallInteger('reminder_offset');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->unique(['channel', 'reminder_type', 'subject_id', 'due_date', 'reminder_offset', 'recipient'], 'reminder_identity_unique');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_deliveries');
    }
};
