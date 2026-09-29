<?php

namespace Database\Factories;

use Agentoom\Core\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Agent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'instructions' => fake()->paragraph(),
            'role' => fake()->sentence(),
            'objective' => fake()->sentence(),
            'prioritize' => [fake()->sentence()],
            'constraints' => [fake()->sentence()],
            'conditional_rules' => [
                [
                    'condition' => fake()->sentence(),
                    'if_true' => fake()->sentence(),
                    'if_false' => fake()->sentence(),
                ],
            ],
            'output_format' => [fake()->sentence()],
            'examples' => [
                [
                    'input' => fake()->paragraph(),
                    'output' => fake()->paragraph(),
                ],
            ],
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_public' => true,
            'slug' => Str::random(16),
            'config' => [],
        ];
    }
}
