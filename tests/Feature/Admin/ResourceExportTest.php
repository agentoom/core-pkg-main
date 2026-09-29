<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ResourceExport;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ResourceExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ResourceType $resourceType;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->be($this->admin);

        $this->resourceType = ResourceType::create([
            'name' => 'Server',
            'user_id' => $this->admin->id,
            'config' => [
                'fields' => [
                    ['name' => 'IP', 'type' => 'text', 'required' => true],
                    ['name' => 'Port', 'type' => 'number'],
                ],
            ],
        ]);

        Resource::create([
            'name' => 'Server 1',
            'description' => 'First server',
            'resource_type_id' => $this->resourceType->id,
            'user_id' => $this->admin->id,
            'data' => ['IP' => '192.168.1.1', 'Port' => '80'],
        ]);
    }

    public function test_it_can_export_resources_to_csv()
    {
        Livewire::test(ResourceExport::class)
            ->set('resource_type_id', $this->resourceType->id)
            ->set('format', 'csv')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_it_can_export_resources_to_xlsx()
    {
        Livewire::test(ResourceExport::class)
            ->set('resource_type_id', $this->resourceType->id)
            ->set('format', 'xlsx')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_it_can_export_resources_to_xls()
    {
        Livewire::test(ResourceExport::class)
            ->set('resource_type_id', $this->resourceType->id)
            ->set('format', 'xls')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_it_shows_error_if_no_resources_to_export()
    {
        $emptyType = ResourceType::create([
            'name' => 'Empty Type',
            'user_id' => $this->admin->id,
            'config' => ['fields' => []],
        ]);

        Livewire::test(ResourceExport::class)
            ->set('resource_type_id', $emptyType->id)
            ->set('format', 'csv')
            ->call('export')
            ->assertHasErrors(['resource_type_id']);
    }
}
