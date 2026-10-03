<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Hasan Aftab',
            'email' => 'hasanaftab007@gmail.com',
            'role' => User::ROLE_ADMIN,
            'password' => bcrypt('hasanaftab007@gmail.com'),
        ]);

        User::factory()->create([
            'name' => 'Rory',
            'email' => 'rory@gmail.com',
            'role' => User::ROLE_ADMIN,
            'password' => bcrypt('rory@gmail.com'),
        ]);

        $this->call([
            AssetTypeSeeder::class,
            CertificateTypeSeeder::class,
            FireTrackerDemoSeeder::class,
        ]);

    }
}
