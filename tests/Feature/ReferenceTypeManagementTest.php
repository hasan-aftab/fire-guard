<?php

use App\Actions\SyncFireNotifications;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\FireNotification;
use App\Models\ReminderDelivery;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\AssetTypeSeeder;
use Database\Seeders\CertificateTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('only verified admins can manage reference data', function () {
    $this->get(route('asset-types.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('certificate-types.index'))->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->create());
    $this->get(route('asset-types.index'))->assertForbidden();
    $this->post(route('asset-types.store'), ['type_name' => 'New Type', 'default_interval_months' => 12])->assertForbidden();
    $this->get(route('certificate-types.index'))->assertForbidden();
    $this->post(route('certificate-types.store'), ['name' => 'New Certificate'])->assertForbidden();
});

test('admins manage asset types while existing schedules remain fixed until a future inspection', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->post(route('asset-types.store'), ['type_name' => 'Fire Extinguisher', 'default_interval_months' => 12])
        ->assertSessionHasNoErrors();
    $type = AssetType::query()->sole();
    $this->get(route('assets.index'))->assertInertia(fn (Assert $page) => $page
        ->where('assetTypes.0.id', $type->id)->where('assetTypes.0.is_active', true));

    $asset = Asset::factory()->for($type)->create(['next_service_due' => '2027-10-01']);
    $this->put(route('asset-types.update', $type), [
        'type_name' => 'Fire Extinguisher Updated', 'default_interval_months' => 6,
    ])->assertSessionHasNoErrors();
    expect($asset->refresh()->assetType->type_name)->toBe('Fire Extinguisher Updated');
    expect($asset->next_service_due->toDateString())->toBe('2027-10-01');
    $this->assertDatabaseCount('inspections', 0);

    $technician = Technician::factory()->create();
    $this->post(route('inspections.store'), [
        'asset_id' => $asset->id, 'technician_id' => $technician->id,
        'inspection_date' => '2026-10-01', 'result' => 'pass',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'next_service_due' => '2027-04-01']);
    $this->assertDatabaseHas('inspections', ['asset_id' => $asset->id, 'next_due_date' => '2027-04-01']);
});

test('inactive asset types remain on existing equipment but cannot be newly assigned', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $staff = User::factory()->create();
    $type = AssetType::factory()->create();
    $other = AssetType::factory()->create();
    $asset = Asset::factory()->for($type)->create();
    $otherAsset = Asset::factory()->for($other)->create();

    $this->actingAs($admin)->delete(route('asset-types.destroy', $type))->assertRedirect();
    expect($type->refresh()->is_active)->toBeFalse();
    $this->assertModelExists($asset);
    $this->actingAs($staff)->get(route('assets.index'))->assertInertia(fn (Assert $page) => $page
        ->where('records.data.0.asset_type.is_active', false));
    $this->post(route('assets.store'), ['site_id' => $asset->site_id, 'asset_type_id' => $type->id])
        ->assertSessionHasErrors('asset_type_id');
    $this->put(route('assets.update', $asset), [
        'site_id' => $asset->site_id, 'asset_type_id' => $type->id, 'serial_number' => 'KEPT-1',
    ])->assertSessionHasNoErrors();
    $this->put(route('assets.update', $otherAsset), [
        'site_id' => $otherAsset->site_id, 'asset_type_id' => $type->id,
    ])->assertSessionHasErrors('asset_type_id');
    expect($asset->refresh()->asset_type_id)->toBe($type->id);
    $this->actingAs($admin)->patch(route('asset-types.restore', $type))->assertRedirect();
    expect($type->refresh()->is_active)->toBeTrue();
});

test('admins manage certificate types without changing certificate expiry status or dates', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->post(route('certificate-types.store'), ['name' => 'Annual Fire Safety Statement'])
        ->assertSessionHasNoErrors();
    $type = CertificateType::query()->sole();
    $certificate = Certificate::factory()->create([
        'certificate_type_id' => $type->id, 'issue_date' => '2026-01-01', 'expiry_date' => '2026-10-31',
    ]);
    DB::table('certificates')->where('id', $certificate->id)->update([
        'legacy_certificate_type' => ' Original certificate wording ',
    ]);
    $this->put(route('certificate-types.update', $type), ['name' => 'Annual Safety Statement'])
        ->assertSessionHasNoErrors();
    expect($certificate->refresh()->certificateType->name)->toBe('Annual Safety Statement');
    expect($certificate->expiry_date->toDateString())->toBe('2026-10-31');
    expect($certificate->legacy_certificate_type)->toBe(' Original certificate wording ');
    expect($certificate->status)->toBe('expiring_soon');
    $this->get(route('certificates.index', ['certificate_type_id' => $type->id]))
        ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)
            ->where('records.data.0.certificate_type.name', 'Annual Safety Statement'));
});

test('inactive certificate types remain on existing records but cannot be newly assigned', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $staff = User::factory()->create();
    $type = CertificateType::factory()->create();
    $other = CertificateType::factory()->create();
    $certificate = Certificate::factory()->create(['certificate_type_id' => $type->id]);
    $otherCertificate = Certificate::factory()->create(['certificate_type_id' => $other->id]);

    $this->actingAs($admin)->delete(route('certificate-types.destroy', $type))->assertRedirect();
    expect($type->refresh()->is_active)->toBeFalse();
    $this->assertModelExists($certificate);
    $this->actingAs($staff)->post(route('certificates.store'), [
        'site_id' => $certificate->site_id, 'certificate_type_id' => $type->id,
        'issue_date' => '2026-01-01', 'expiry_date' => '2027-01-01',
    ])->assertSessionHasErrors('certificate_type_id');
    $this->put(route('certificates.update', $certificate), [
        'site_id' => $certificate->site_id, 'certificate_type_id' => $type->id,
        'issue_date' => '2026-01-01', 'expiry_date' => '2027-01-01',
    ])->assertSessionHasNoErrors();
    $this->put(route('certificates.update', $otherCertificate), [
        'site_id' => $otherCertificate->site_id, 'certificate_type_id' => $type->id,
        'issue_date' => '2026-01-01', 'expiry_date' => '2027-01-01',
    ])->assertSessionHasErrors('certificate_type_id');
    $this->actingAs($admin)->patch(route('certificate-types.restore', $type))->assertRedirect();
    expect($type->refresh()->is_active)->toBeTrue();
});

test('duplicate names are rejected after case and whitespace normalization', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $assetType = AssetType::factory()->create(['type_name' => 'Fire Alarm']);
    $certificateType = CertificateType::factory()->create(['name' => 'Safety Certificate']);
    $this->post(route('asset-types.store'), ['type_name' => ' fire   alarm ', 'default_interval_months' => 12])
        ->assertSessionHasErrors('type_name');
    $this->post(route('certificate-types.store'), ['name' => ' safety   certificate '])
        ->assertSessionHasErrors('name');
    $this->put(route('asset-types.update', $assetType), [
        'type_name' => 'Fire Alarm', 'default_interval_months' => 6,
    ])->assertSessionHasNoErrors();
    $this->put(route('certificate-types.update', $certificateType), ['name' => 'Safety Certificate'])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseCount('asset_types', 1);
    $this->assertDatabaseCount('certificate_types', 1);
});

test('seeders initialize fresh types without overwriting later business edits', function () {
    $this->seed(AssetTypeSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    $assetType = AssetType::query()->firstOrFail();
    $certificateType = CertificateType::query()->firstOrFail();
    $assetType->update(['default_interval_months' => 18, 'is_active' => false]);
    $certificateType->update(['is_active' => false]);
    $this->seed(AssetTypeSeeder::class);
    $this->seed(CertificateTypeSeeder::class);
    expect($assetType->refresh()->default_interval_months)->toBe(18);
    expect($assetType->is_active)->toBeFalse();
    expect($certificateType->refresh()->is_active)->toBeFalse();
    $this->assertDatabaseCount('asset_types', 9);
    $this->assertDatabaseCount('certificate_types', 5);
});

test('foreign keys retain operational records when a referenced type is deactivated', function () {
    $asset = Asset::factory()->create();
    $certificate = Certificate::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->delete(route('asset-types.destroy', $asset->assetType))->assertRedirect();
    $this->delete(route('certificate-types.destroy', $certificate->certificateType))->assertRedirect();
    $this->assertModelExists($asset);
    $this->assertModelExists($certificate);
    expect(fn () => $asset->assetType->delete())->toThrow(QueryException::class);
    expect(fn () => $certificate->certificateType->delete())->toThrow(QueryException::class);
});

test('type edits change reminder names while eligibility still uses saved due and expiry dates', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $asset = Asset::factory()->create(['next_service_due' => '2026-10-08']);
    $certificate = Certificate::factory()->create(['expiry_date' => '2026-10-08']);
    $assetType = $asset->assetType;
    $certificateType = $certificate->certificateType;
    $this->put(route('asset-types.update', $assetType), [
        'type_name' => 'Renamed Equipment',
        'default_interval_months' => 24,
    ])->assertSessionHasNoErrors();
    $this->put(route('certificate-types.update', $certificateType), ['name' => 'Renamed Certificate'])
        ->assertSessionHasNoErrors();
    Mail::fake();

    $this->artisan('fire-tracker:send-reminders')->assertExitCode(0);

    $assetDelivery = ReminderDelivery::query()->where('reminder_type', 'asset')->sole();
    $certificateDelivery = ReminderDelivery::query()->where('reminder_type', 'certificate')->sole();
    expect($assetDelivery->subject_name)->toContain('Renamed Equipment');
    expect($certificateDelivery->subject_name)->toContain('Renamed Certificate');
    expect($assetDelivery->due_date->toDateString())->toBe('2026-10-08');
    expect($certificateDelivery->due_date->toDateString())->toBe('2026-10-08');
    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'next_service_due' => '2026-10-08']);
    $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'expiry_date' => '2026-10-08']);
});

test('type name edits refresh current notifications for other users', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $viewer = User::factory()->create();
    $asset = Asset::factory()->create(['next_service_due' => '2026-10-08']);
    $certificate = Certificate::factory()->create(['expiry_date' => '2026-10-08']);
    app(SyncFireNotifications::class)->handle($viewer);
    $assetType = $asset->assetType;
    $certificateType = $certificate->certificateType;

    $this->actingAs($admin)->put(route('asset-types.update', $assetType), [
        'type_name' => 'Current Equipment Name',
        'default_interval_months' => 6,
    ])->assertSessionHasNoErrors();
    $this->put(route('certificate-types.update', $certificateType), [
        'name' => 'Current Certificate Name',
    ])->assertSessionHasNoErrors();

    expect(FireNotification::query()->where('user_id', $viewer->id)->where('entity_type', 'asset')->sole()->message)
        ->toContain('Current Equipment Name');
    expect(FireNotification::query()->where('user_id', $viewer->id)->where('entity_type', 'certificate')->sole()->message)
        ->toContain('Current Certificate Name');
});

test('the certificate type table migration leaves existing operational tables untouched', function () {
    config()->set('database.connections.legacy_reference_test', [
        'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
    ]);
    DB::setDefaultConnection('legacy_reference_test');
    try {
        Schema::create('asset_types', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->string('certificate_type');
        });
        DB::table('certificates')->insert(['certificate_type' => ' Original legacy value ']);

        $migration = require database_path('migrations/2026_09_30_185803_create_certificate_types_table.php');
        $migration->up();
        expect(Schema::hasTable('certificate_types'))->toBeTrue();
        expect(Schema::hasColumn('asset_types', 'is_active'))->toBeFalse();
        expect(Schema::hasColumn('certificates', 'certificate_type_id'))->toBeFalse();
        expect(DB::table('certificates')->value('certificate_type'))->toBe(' Original legacy value ');
        $migration->down();
        expect(Schema::hasTable('certificate_types'))->toBeFalse();
        expect(DB::table('certificates')->value('certificate_type'))->toBe(' Original legacy value ');
    } finally {
        DB::setDefaultConnection('sqlite');
        DB::purge('legacy_reference_test');
    }
});
