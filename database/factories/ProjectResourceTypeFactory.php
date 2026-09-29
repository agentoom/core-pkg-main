<?php

namespace Database\Factories;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectResourceTypeFactory extends Factory
{
    protected $model = ProjectResourceType::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
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
