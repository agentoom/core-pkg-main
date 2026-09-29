<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ResourceImport;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ResourceUpsertImportTest extends TestCase
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
                    ['name' => 'SKU', 'type' => 'text', 'required' => true],
                    ['name' => 'Status', 'type' => 'text'],
                ],
            ],
        ]);
    }

    public function test_it_upserts_resources_by_name()
    {
        Storage::fake('local');

        // Create an existing resource
        Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'user_id' => $this->admin->id,
            'name' => 'Original Server',
            'description' => 'Old Description',
            'data' => ['SKU' => 'SKU1', 'Status' => 'Offline'],
        ]);

        $csvContent = "name,SKU,Status,Description\nOriginal Server,SKU1,Online,New Description\nNew Server,SKU2,Online,New Server Desc\n";
        $file = UploadedFile::fake()->createWithContent('servers.csv', $csvContent);

        Livewire::test(ResourceImport::class)
            ->set('resource_type_id', $this->resourceType->id)
            ->set('file', $file)
            ->call('next')
            ->set('mapping.name', '0')
            ->set('mapping.SKU', '1')
            ->set('mapping.Status', '2')
            ->set('mapping.description', '3')
            ->set('unique_field', 'name')
            ->call('import');

        $this->assertEquals(2, Resource::count());

        $updatedResource = Resource::where('name', 'Original Server')->first();
        $this->assertEquals('New Description', $updatedResource->description);
        $this->assertEquals('Online', $updatedResource->data['Status']);

        $newResource = Resource::where('name', 'New Server')->first();
        $this->assertNotNull($newResource);
    }

    public function test_it_upserts_resources_by_dynamic_field()
    {
        Storage::fake('local');

        // Create an existing resource
        Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'user_id' => $this->admin->id,
            'name' => 'Server X',
            'data' => ['SKU' => 'UNIQUE_SKU_123', 'Status' => 'Offline'],
        ]);

        $csvContent = "name,SKU,Status\nUpdated Name,UNIQUE_SKU_123,Online\n";
        $file = UploadedFile::fake()->createWithContent('servers.csv', $csvContent);

        Livewire::test(ResourceImport::class)
            ->set('resource_type_id', $this->resourceType->id)
            ->set('file', $file)
            ->call('next')
            ->set('mapping.name', '0')
            ->set('mapping.SKU', '1')
            ->set('mapping.Status', '2')
            ->set('unique_field', 'SKU')
            ->call('import');

        $this->assertEquals(1, Resource::count());

        $resource = Resource::where('resource_type_id', $this->resourceType->id)->first();
        $this->assertEquals('Updated Name', $resource->name);
        $this->assertEquals('Online', $resource->data['Status']);
    }

    public function test_it_does_not_upsert_if_no_unique_field_selected()
    {
        Storage::fake('local');

        Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'user_id' => $this->admin->id,
            'name' => 'Server 1',
            'data' => ['SKU' => 'SKU1'],
        ]);

        $csvContent = "name,SKU\nServer 1,SKU1\n";
        $file = UploadedFile::fake()->createWithContent('servers.csv', $csvContent);

        Livewire::test(ResourceImport::class)
            ->set('resource_type_id', $this->resourceType->id)
            ->set('file', $file)
            ->call('next')
            ->set('mapping.name', '0')
            ->set('mapping.SKU', '1')
            ->set('unique_field', '')
            ->call('import');

        $this->assertEquals(2, Resource::count());
    }
}
