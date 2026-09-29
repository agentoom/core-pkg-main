<?php

namespace Database\Factories;

use Agentoom\Core\Models\AgentSkill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentSkill>
 */
class AgentSkillFactory extends Factory
{
    protected $model = AgentSkill::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'instructions' => $this->faker->paragraph(),
            'musts' => [$this->faker->sentence(), $this->faker->sentence()],
            'donts' => [$this->faker->sentence()],
            'if_this_then' => ['on_user_request' => 'analyze_intent'],
            'tools' => [],
            'is_active' => true,
        ];
    }
}
