<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Jobs\ProcessAgentKnowledgeJob;
use Agentoom\Core\Jobs\SyncKnowledgeSummary;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class AgentKnowledgeManager extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $agentId;

    public $knowledgeInputs = [];

    public $nextInputId = 2;

    public bool $show_delete_file_modal = false;

    public ?int $file_id_to_delete = null;

    public bool $enable_summarization = false;

    public array $uploadFailures = [];

    public function mount(Agent $agent)
    {
        $this->agentId = $agent->id;
    }

    #[Computed]
    public function agent(): Agent
    {
        return Agent::find($this->agentId);
    }

    public function addFileInput()
    {
        $this->knowledgeInputs[] = ['id' => $this->nextInputId++, 'type' => 'file', 'file' => null, 'url' => null, 'text' => null, 'upload_key' => (string) Str::uuid()];
    }

    public function addUrlInput()
    {
        $this->knowledgeInputs[] = ['id' => $this->nextInputId++, 'type' => 'url', 'file' => null, 'url' => null, 'text' => null, 'upload_key' => null];
    }

    public function addTextInput()
    {
        $this->knowledgeInputs[] = ['id' => $this->nextInputId++, 'type' => 'text', 'file' => null, 'url' => null, 'text' => null, 'upload_key' => null];
    }

    public function handleFileUploadError(int $index): void
    {
        if (! isset($this->knowledgeInputs[$index])) {
            return;
        }

        $this->knowledgeInputs[$index]['file'] = null;
        $this->knowledgeInputs[$index]['upload_key'] = (string) Str::uuid();
        $this->uploadFailures[$index] = true;
        $this->resetValidation("knowledgeInputs.{$index}.file");
        $this->addError("knowledgeInputs.{$index}.file", __('agentoom-core::admin.knowledge.validation_file_too_large'));
    }

    public function updated($name, $value): void
    {
        if (str_ends_with((string) $name, '.file') && $value !== null) {
            preg_match('/knowledgeInputs\\.(\\d+)\\.file/', (string) $name, $matches);
            if (isset($matches[1])) {
                unset($this->uploadFailures[(int) $matches[1]]);
            }

            $this->resetValidation($name);
        }
    }

    public function removeInput($index)
    {
        if (count($this->knowledgeInputs) > 1) {
            unset($this->knowledgeInputs[$index]);
            $this->knowledgeInputs = array_values($this->knowledgeInputs);
            $this->uploadFailures = [];
        }
    }

    public function saveKnowledge()
    {
        foreach ($this->knowledgeInputs as $index => $input) {
            if (($input['type'] ?? 'file') !== AgentKnowledgeFile::TYPE_FILE) {
                continue;
            }

            if (($this->uploadFailures[$index] ?? false) && ! isset($input['file'])) {
                $this->addError("knowledgeInputs.{$index}.file", __('agentoom-core::admin.knowledge.validation_file_too_large'));

                return;
            }
        }

        $messages = [];
        $attributes = [];
        foreach ($this->knowledgeInputs as $index => $input) {
            $messages["knowledgeInputs.{$index}.file.required_if"] = __('agentoom-core::admin.knowledge.validation_file_required');
            $messages["knowledgeInputs.{$index}.file.max"] = __('agentoom-core::admin.knowledge.validation_file_too_large');
            $messages["knowledgeInputs.{$index}.file.file"] = __('agentoom-core::admin.knowledge.validation_file_invalid');
            $messages["knowledgeInputs.{$index}.file.uploaded"] = __('agentoom-core::admin.knowledge.validation_file_too_large');
            $messages["knowledgeInputs.{$index}.url.required_if"] = __('agentoom-core::admin.knowledge.validation_url_required');
            $messages["knowledgeInputs.{$index}.url.url"] = __('agentoom-core::admin.knowledge.validation_url_invalid');
            $messages["knowledgeInputs.{$index}.text.required_if"] = __('agentoom-core::admin.knowledge.validation_text_required');
            $attributes["knowledgeInputs.{$index}.file"] = __('agentoom-core::admin.knowledge.file');
            $attributes["knowledgeInputs.{$index}.url"] = __('agentoom-core::admin.knowledge.url');
            $attributes["knowledgeInputs.{$index}.text"] = __('agentoom-core::admin.knowledge.text');
        }

        $this->validate([
            'knowledgeInputs.*.file' => 'required_if:knowledgeInputs.*.type,file|nullable|file|max:10240',
            'knowledgeInputs.*.url' => 'required_if:knowledgeInputs.*.type,url|nullable|url',
            'knowledgeInputs.*.text' => 'required_if:knowledgeInputs.*.type,text|nullable|string',
        ], $messages, $attributes);

        $savedCount = 0;

        foreach ($this->knowledgeInputs as $input) {
            if (! isset($input['type'])) {
                continue;
            }
            if ($input['type'] === AgentKnowledgeFile::TYPE_FILE) {
                $file = $input['file'];
                if (! $file) {
                    continue;
                }

                $path = $file->store('knowledge', 'public');

                $newFile = $this->agent->knowledgeFiles()->create([
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

                $newFile = $this->agent->knowledgeFiles()->create([
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

                $newFile = $this->agent->knowledgeFiles()->create([
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
        $this->uploadFailures = [];
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
        if (! $this->enable_summarization) {
            return;
        }

        $files = $this->agent->knowledgeFiles()
            ->whereIn('status', [AgentKnowledgeFile::STATUS_COMPLETED, AgentKnowledgeFile::STATUS_CHUNKED])
            ->whereNull('summary')
            ->get();

        if ($files->isEmpty()) {
            return;
        }

        foreach ($files as $file) {
            $file->update(['status' => AgentKnowledgeFile::STATUS_SYNCING]);
        }

        SyncKnowledgeSummary::dispatch($this->agent->id, $this->agent->project_id);
    }

    public function render()
    {
        // Ensure all inputs have an ID for stability
        foreach ($this->knowledgeInputs as $index => $input) {
            if (! isset($input['id'])) {
                $this->knowledgeInputs[$index]['id'] = $this->nextInputId++;
            }

            if (($input['type'] ?? 'file') === 'file' && ! isset($input['upload_key'])) {
                $this->knowledgeInputs[$index]['upload_key'] = (string) Str::uuid();
            }
        }

        $files = $this->agent->knowledgeFiles()->withCount('knowledge')->orderBy('created_at', 'desc')->get();
        $totalFiles = $files->count();
        $totalFilesSynchronized = $files->where('status', AgentKnowledgeFile::STATUS_COMPLETED)->count();
        $totalFilesUnsynchronized = $files->where('status', '!=', AgentKnowledgeFile::STATUS_COMPLETED)->count();

        return view('agentoom-core::livewire.admin.agent-knowledge-manager', [
            'agent' => $this->agent,
            'files' => $files,
            'totalFiles' => $totalFiles,
            'totalFilesSynchronized' => $totalFilesSynchronized,
            'totalFilesUnsynchronized' => $totalFilesUnsynchronized,
        ])->layout('agentoom-core::layouts.admin');
    }
}
