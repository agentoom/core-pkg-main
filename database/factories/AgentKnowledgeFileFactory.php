<?php

namespace Database\Factories;

use Agentoom\Core\Models\AgentKnowledgeFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentKnowledgeFile>
 */
class AgentKnowledgeFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AgentKnowledgeFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word().'.txt',
            'type' => AgentKnowledgeFile::TYPE_FILE,
            'path' => 'knowledge/'.$this->faker->uuid().'.txt',
            'status' => AgentKnowledgeFile::STATUS_PENDING,
        ];
    }
}
