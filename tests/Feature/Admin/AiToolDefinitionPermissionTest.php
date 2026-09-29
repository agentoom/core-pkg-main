<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use Agentoom\Sentinel\Livewire\Admin\AiToolDefinitionCreateEdit;
use Agentoom\Sentinel\Livewire\Admin\AiToolDefinitionIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AiToolDefinitionPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
    }

    public function test_components_can_render()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Livewire::test(AiToolDefinitionIndex::class)->assertStatus(200);
        Livewire::test(AiToolDefinitionCreateEdit::class)->assertStatus(200);
    }
}
