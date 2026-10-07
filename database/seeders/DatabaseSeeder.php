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
            'name' => 'Muhammad Web',
            'email' => 'muhammadwebdeveloper281@gmail.com',
            'role' => User::ROLE_ADMIN,
            'password' => bcrypt('muhammadwebdeveloper281@gmail.com'),
        ]);

        User::factory()->create([
            'name' => 'Rory',
            'email' => 'rwilliamstewart@gmail.com',
            'role' => User::ROLE_ADMIN,
            'password' => bcrypt('rwilliamstewart@gmail.com'),
        ]);

        $this->call([
            AssetTypeSeeder::class,
            CertificateTypeSeeder::class,
            FireTrackerDemoSeeder::class,
        ]);

    }
}
