<?php

namespace Database\Seeders;

use Agentoom\Core\Models\Language;
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
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        Language::create(['name' => 'Italian', 'code' => 'it', 'is_active' => true]);
        Language::create(['name' => 'French', 'code' => 'fr', 'is_active' => true]);
        Language::create(['name' => 'Spanish', 'code' => 'es', 'is_active' => true]);
    }
}
