<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class EndpointLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_shows_endpoint_pipelines_to_endpoints(): void
    {
        $user = User::factory()->create();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $endpointPipeline = Pipeline::factory()->endpoint()->create(['is_active' => true, 'name' => 'Endpoint Pipeline']);
        $standardPipeline = Pipeline::factory()->create(['is_active' => true, 'name' => 'Standard Pipeline']);

        Livewire::test('agentoom-core::admin.endpoint-create-edit')
            ->assertViewHas('pipelines', function ($pipelines) use ($endpointPipeline, $standardPipeline) {
                return $pipelines->contains($endpointPipeline) && ! $pipelines->contains($standardPipeline);
            });
    }
}
