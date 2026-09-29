<?php

namespace Database\Factories;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'timezone' => 'UTC',
            'model' => Agent::class,
            'model_id' => Agent::factory(),
            'instructions' => $this->faker->paragraph,
            'active' => true,
            'user_id' => User::factory(),
        ];
    }
}
