<?php

namespace Database\Factories;

use Agentoom\Core\Models\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => Pipeline::TYPE_STANDARD,
            'description' => $this->faker->paragraph,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the pipeline is an endpoint pipeline.
     */
    public function endpoint(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Pipeline::TYPE_ENDPOINT,
        ]);
    }
}
