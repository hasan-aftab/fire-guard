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
            $table->string('email')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('site_name');
            $table->text('address');
            $table->string('suburb_region')->nullable();
            $table->text('access_notes')->nullable();
            $table->string('site_contact')->nullable();
            $table->string('site_phone')->nullable();
            $table->timestamps();
        });
        Schema::create('asset_types', function (Blueprint $table): void {
            $table->id();
            $table->string('type_name')->unique();
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('default_interval_months')->default(12);
            $table->timestamps();
        });
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
        Schema::create('technicians', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->date('inspection_date');
            $table->string('result');
            $table->text('notes')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('photo_url')->nullable();
            $table->timestamps();
        });
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certificate_type');
            $table->date('issue_date');
            $table->date('expiry_date')->index();
            $table->string('issued_by')->nullable();
            $table->text('document_url')->nullable();
            $table->string('status')->default('valid');
            $table->timestamps();
        });
        Schema::create('fire_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_type');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'type', 'entity_type', 'entity_id']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        foreach (['fire_notifications', 'certificates', 'inspections', 'technicians', 'assets', 'asset_types', 'sites', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
