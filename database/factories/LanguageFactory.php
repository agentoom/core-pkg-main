<?php

namespace Database\Factories;

use Agentoom\Core\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->languageCode(),
            'code' => $this->faker->unique()->languageCode(),
            'is_active' => true,
        ];
    }
}
