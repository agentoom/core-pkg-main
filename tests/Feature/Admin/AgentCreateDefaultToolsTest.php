<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Ai\Tools\Public\ManageAgentKnowledgeTool;
use Agentoom\Core\Ai\Tools\Public\ManageAgentLearnedSkillsTool;
use Agentoom\Core\Ai\Tools\Public\SearchAgentKnowledgeTool;
use Agentoom\Core\Ai\Tools\Public\SearchAgentLearnedSkillsTool;
use Agentoom\Core\Livewire\Admin\AgentCreateEdit;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AgentCreateDefaultToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_tools_are_selected_for_new_agent(): void
    {
        Role::create(['name' => 'superadmin']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $toolIds = Tool::whereIn('class_name', [
            SearchAgentKnowledgeTool::class,
            ManageAgentKnowledgeTool::class,
            SearchAgentLearnedSkillsTool::class,
            ManageAgentLearnedSkillsTool::class,
        ])->pluck('id')->toArray();

        Livewire::test(AgentCreateEdit::class, ['agent' => null])
            ->assertSet('selected_tools', $toolIds);
    }
}
