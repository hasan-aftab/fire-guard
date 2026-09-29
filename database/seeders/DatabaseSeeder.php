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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Hasan Aftab',
            'email' => 'hasanaftab007@gmail.com',
            'password' => bcrypt('hasanaftab007@gmail.com'),
        ]);

        User::factory()->create([
            'name' => 'Rory',
            'email' => 'rory@gmail.com',
            'password' => bcrypt('rory@gmail.com'),
        ]);
    }
}
