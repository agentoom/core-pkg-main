<?php

namespace Database\Seeders;

use Agentoom\Core\Models\AgentSkill;
use Illuminate\Database\Seeder;

class AgentSkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AgentSkill::factory()->count(5)->create();
    }
}
