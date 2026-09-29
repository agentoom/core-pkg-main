<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Jobs\ProcessAgentKnowledgeJob;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Agentoom\Core\Models\Project;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProjectKnowledgeManager extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $projectId = null;

    public bool $is_embedded = false;

    public $knowledgeInputs = [];

    public $nextInputId = 2;

    public bool $show_delete_file_modal = false;

    public ?int $file_id_to_delete = null;

    public function mount(?Project $project = null, bool $is_embedded = false)
    {
        $this->projectId = $project?->id;
        $this->is_embedded = $is_embedded;
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId ? Project::find($this->projectId) : null;
    }

    public function addFileInput()
    {
        $this->knowledgeInputs[] = ['id' => $this->nextInputId++, 'type' => 'file', 'file' => null, 'url' => null, 'text' => null];
    }

    public function addUrlInput()
    {
        $this->knowledgeInputs[] = ['id' => $this->nextInputId++, 'type' => 'url', 'file' => null, 'url' => null, 'text' => null];
    }

    public function addTextInput()
    {
        $this->knowledgeInputs[] = ['id' => $this->nextInputId++, 'type' => 'text', 'file' => null, 'url' => null, 'text' => null];
    }

    public function removeInput($index)
    {
        if (count($this->knowledgeInputs) > 1) {
            unset($this->knowledgeInputs[$index]);
            $this->knowledgeInputs = array_values($this->knowledgeInputs);
        }
    }

    public function saveKnowledge()
    {
        $this->validate([
            'knowledgeInputs.*.file' => 'required_if:knowledgeInputs.*.type,file|nullable|file|max:10240',
            'knowledgeInputs.*.url' => 'required_if:knowledgeInputs.*.type,url|nullable|url',
            'knowledgeInputs.*.text' => 'required_if:knowledgeInputs.*.type,text|nullable|string',
        ]);

        if (! $this->project) {
            return;
        }

        $savedCount = 0;

        foreach ($this->knowledgeInputs as $input) {
            if ($input['type'] === AgentKnowledgeFile::TYPE_FILE) {
                $file = $input['file'];
                if (! $file) {
                    continue;
                }

                $path = $file->store('knowledge', 'public');

                $newFile = $this->project->knowledgeFiles()->create([
                    'type' => AgentKnowledgeFile::TYPE_FILE,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'status' => AgentKnowledgeFile::STATUS_PENDING,
                ]);
            } elseif ($input['type'] === AgentKnowledgeFile::TYPE_TEXT) {
                $text = $input['text'];
                if (! $text) {
                    continue;
                }

                $filename = 'text_'.uniqid().'.txt';
                $path = 'knowledge/'.$filename;
                Storage::disk('public')->put($path, $text);

                $newFile = $this->project->knowledgeFiles()->create([
                    'type' => AgentKnowledgeFile::TYPE_TEXT,
                    'name' => 'Manual Entry ('.now()->format('Y-m-d H:i').')',
                    'path' => $path,
                    'status' => AgentKnowledgeFile::STATUS_PENDING,
                ]);
            } else {
                $url = $input['url'];
                if (! $url) {
                    continue;
                }

                $newFile = $this->project->knowledgeFiles()->create([
                    'type' => AgentKnowledgeFile::TYPE_URL,
                    'name' => $url,
                    'path' => $url,
                    'status' => AgentKnowledgeFile::STATUS_PENDING,
                ]);
            }

            ProcessAgentKnowledgeJob::dispatch($newFile->id);

            $savedCount++;
        }

        if ($savedCount > 0) {
            session()->flash('success', __('agentoom-core::admin.knowledge.files_uploaded_successfully', ['count' => $savedCount]));
        }

        $this->knowledgeInputs = [];
    }

    public function confirmDeleteFile($id)
    {
        $this->file_id_to_delete = $id;
        $this->show_delete_file_modal = true;
    }

    public function deleteFile()
    {
        if ($this->file_id_to_delete) {
            AgentKnowledgeFile::find($this->file_id_to_delete)?->delete();
            session()->flash('success', __('agentoom-core::admin.knowledge.file_deleted'));
        }
        $this->show_delete_file_modal = false;
        $this->file_id_to_delete = null;
    }

    public function synchronizeFiles()
    {
        if (! $this->project) {
            return;
        }

        $files = $this->project->knowledgeFiles()->where('status', '!=', AgentKnowledgeFile::STATUS_COMPLETED)->get();
        foreach ($files as $file) {
            $file->update(['status' => AgentKnowledgeFile::STATUS_SYNCING]);
            $file->synchronize();
        }
    }

    public function render()
    {
        // Ensure all inputs have an ID for stability
        foreach ($this->knowledgeInputs as $index => $input) {
            if (! isset($input['id'])) {
                $this->knowledgeInputs[$index]['id'] = $this->nextInputId++;
            }
        }

        if ($this->project) {
            $files = $this->project->knowledgeFiles()->withCount('knowledge')->orderBy('created_at', 'desc')->get();
            $totalFiles = $files->count();
            $totalFilesSynchronized = $files->whereNotNull('summary')->count();
            $totalFilesUnsynchronized = $files->whereNull('summary')->count();
        } else {
            $files = collect();
            $totalFiles = 0;
            $totalFilesSynchronized = 0;
            $totalFilesUnsynchronized = 0;
        }

        return view('agentoom-core::livewire.admin.project-knowledge-manager', [
            'project' => $this->project,
            'files' => $files,
            'totalFiles' => $totalFiles,
            'totalFilesSynchronized' => $totalFilesSynchronized,
            'totalFilesUnsynchronized' => $totalFilesUnsynchronized,
        ])->layout('agentoom-core::layouts.admin');
    }
}
