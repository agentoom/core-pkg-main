<?php

namespace Database\Factories;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectResourceFactory extends Factory
{
    protected $model = ProjectResource::class;

    public function definition(): array
    {
        return [
            'project_resource_type_id' => ProjectResourceType::factory(),
            'project_id' => Project::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'data' => [
                'field1' => $this->faker->word(),
            ],
        ];
    }
}
