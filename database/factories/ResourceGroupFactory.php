<?php

namespace Database\Factories;

use Agentoom\Core\Models\ResourceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceGroupFactory extends Factory
{
    protected $model = ResourceGroup::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->word()],
            'icon' => 'cube',
            'description' => $this->faker->sentence(),
        ];
    }
}
