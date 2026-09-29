<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ProjectResourceImport;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

class ProjectResourceImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected ProjectResourceType $projectResourceType;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->be($this->admin);

        $this->project = Project::factory()->create(['user_id' => $this->admin->id]);

        $this->projectResourceType = ProjectResourceType::create([
            'project_id' => $this->project->id,
            'name' => ['en' => 'Server'],
            'config' => [
                'fields' => [
                    ['name' => 'IP', 'type' => 'text', 'required' => true],
                    ['name' => 'Port', 'type' => 'number'],
                ],
            ],
        ]);
    }

    public function test_it_can_parse_csv_headers()
    {
        Storage::fake('local');
        $csvContent = "IP,Port,Description\n127.0.0.1,80,Localhost\n";
        $file = UploadedFile::fake()->createWithContent('servers.csv', $csvContent);

        Livewire::test(ProjectResourceImport::class)
            ->set('file', $file)
            ->assertSet('headers', ['IP', 'Port', 'Description']);
    }

    public function test_it_can_parse_xlsx_headers()
    {
        Storage::fake('local');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'IP');
        $sheet->setCellValue('B1', 'Port');
        $sheet->setCellValue('C1', 'Description');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        $file = UploadedFile::fake()->createWithContent('servers.xlsx', $content);

        Livewire::test(ProjectResourceImport::class)
            ->set('file', $file)
            ->assertSet('headers', ['IP', 'Port', 'Description']);
    }

    public function test_it_imports_project_resources_from_xlsx_correctly()
    {
        Storage::fake('local');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'name');
        $sheet->setCellValue('B1', 'IP');
        $sheet->setCellValue('C1', 'Port');

        $sheet->setCellValue('A2', 'Server X');
        $sheet->setCellValue('B2', '10.0.0.1');
        $sheet->setCellValue('C2', '8080');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        $file = UploadedFile::fake()->createWithContent('servers.xlsx', $content);

        Livewire::test(ProjectResourceImport::class)
            ->set('project_resource_type_id', $this->projectResourceType->id)
            ->set('file', $file)
            ->call('next')
            ->set('mapping.name', '0')
            ->set('mapping.IP', '1')
            ->set('mapping.Port', '2')
            ->call('import')
            ->assertRedirect(route('admin.projects.resources.index', ['project_resource_type_id' => $this->projectResourceType->id]));

        $this->assertDatabaseHas('project_resources', [
            'name' => 'Server X',
            'project_resource_type_id' => $this->projectResourceType->id,
        ]);

        $resource = ProjectResource::where('name', 'Server X')->first();
        $this->assertEquals('10.0.0.1', $resource->data['IP']);
        $this->assertEquals('8080', $resource->data['Port']);
    }

    public function test_it_automaps_fields_by_name()
    {
        Storage::fake('local');
        $csvContent = "IP,Port,Other\n127.0.0.1,80,Value\n";
        $file = UploadedFile::fake()->createWithContent('servers.csv', $csvContent);

        Livewire::test(ProjectResourceImport::class)
            ->set('project_resource_type_id', $this->projectResourceType->id)
            ->set('file', $file)
            ->assertSet('mapping.IP', '0')
            ->assertSet('mapping.Port', '1');
    }

    public function test_it_imports_project_resources_correctly()
    {
        Storage::fake('local');
        $csvContent = "name,IP,Port\nServer 1,192.168.1.1,443\nServer 2,192.168.1.2,80\n";
        $file = UploadedFile::fake()->createWithContent('servers.csv', $csvContent);

        Livewire::test(ProjectResourceImport::class)
            ->set('project_resource_type_id', $this->projectResourceType->id)
            ->set('file', $file)
            ->call('next')
            ->set('mapping.name', '0')
            ->set('mapping.IP', '1')
            ->set('mapping.Port', '2')
            ->call('import')
            ->assertRedirect(route('admin.projects.resources.index', ['project_resource_type_id' => $this->projectResourceType->id]));

        $this->assertEquals(2, ProjectResource::count());
        $this->assertDatabaseHas('project_resources', [
            'name' => 'Server 1',
            'project_resource_type_id' => $this->projectResourceType->id,
        ]);

        $resource = ProjectResource::where('name', 'Server 1')->first();
        $this->assertEquals('192.168.1.1', $resource->data['IP']);
        $this->assertEquals('443', $resource->data['Port']);
    }
}
