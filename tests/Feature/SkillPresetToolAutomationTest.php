<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Livewire\Admin\Config\SkillPresets;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class SkillPresetToolAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_skill_automatically_adds_tools_to_preset(): void
    {
        Role::create(['name' => 'superadmin']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $tool1 = Tool::create([
            'name' => 'Tool 1',
            'class_name' => 'Tool1',
            'description' => 'Description 1',
            'is_available' => true,
        ]);

        $tool2 = Tool::create([
            'name' => 'Tool 2',
            'class_name' => 'Tool2',
            'description' => 'Description 2',
            'is_available' => true,
        ]);

        $skill = AgentSkill::create([
            'name' => 'Skill with tools',
            'slug' => 'skill-with-tools',
            'tools' => [(string) $tool1->id, (string) $tool2->id],
            'is_active' => true,
        ]);

        Livewire::test(SkillPresets::class)
            ->set('selected_skills', [$skill->id])
            ->assertSet('selected_tools', [(string) $tool1->id, (string) $tool2->id]);
    }
}
