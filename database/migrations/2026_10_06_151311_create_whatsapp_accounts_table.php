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
        Schema::create('whatsapp_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('display_phone_number', 24);
            $table->string('phone_number_id', 32)->unique();
            $table->string('waba_id', 32)->index();
            $table->text('access_token');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_test')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedTinyInteger('default_slot')->nullable()->unique();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
