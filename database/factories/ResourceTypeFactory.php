<?php

namespace Database\Factories;

use Agentoom\Core\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceTypeFactory extends Factory
{
    protected $model = ResourceType::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->word()],
            'description' => $this->faker->sentence(),
            'config' => [
                'fields' => [
                    ['name' => 'field1', 'type' => 'text', 'required' => true],
                ],
            ],
        ];
    }
}
