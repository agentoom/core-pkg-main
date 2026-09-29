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

class ResourceImportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_import_processes_large_file_in_chunks()
    {
        Storage::fake('local');

        $resourceType = ResourceType::create([
            'name' => 'Bulk Type',
            'user_id' => auth()->id(),
            'config' => [
                'fields' => [['name' => 'age', 'type' => 'number']],
            ],
        ]);

        // Create a CSV with 600 records (batch size is 500)
        $headers = ['name', 'description', 'age'];
        $content = implode(',', $headers)."\n";
        for ($i = 1; $i <= 600; $i++) {
            $content .= "Name {$i},Description {$i},{$i}\n";
        }

        $file = UploadedFile::fake()->createWithContent('bulk.csv', $content);

        Livewire::test(ResourceImport::class)
            ->set('resource_type_id', $resourceType->id)
            ->set('file', $file)
            ->call('next')
            ->set('mapping', [
                'name' => '0',
                'description' => '1',
                'age' => '2',
            ])
            ->call('import')
            ->assertHasNoErrors();

        $this->assertEquals(600, Resource::count());
        $this->assertDatabaseHas('resources', [
            'name' => 'Name 1',
            'resource_type_id' => $resourceType->id,
        ]);
        $this->assertDatabaseHas('resources', [
            'name' => 'Name 600',
            'resource_type_id' => $resourceType->id,
        ]);
    }
}
