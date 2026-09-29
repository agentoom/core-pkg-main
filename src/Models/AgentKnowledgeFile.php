<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Jobs\SyncKnowledgeSummary;
use Database\Factories\AgentKnowledgeFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class AgentKnowledgeFile extends Model
{
    use HasFactory;

    public const TYPE_FILE = 'file';

    public const TYPE_URL = 'url';

    public const TYPE_TEXT = 'text';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_CHUNKED = 'chunked';

    public const STATUS_SYNCING = 'syncing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'agent_id',
        'project_id',
        'type',
        'name',
        'path',
        'status',
        'last_error',
        'summary',
    ];

    protected $appends = ['synchronized'];

    public function getSynchronizedAttribute(): bool
    {
        return ! is_null($this->summary);
    }

    public function synchronize(): void
    {
        SyncKnowledgeSummary::dispatch($this->agent_id, $this->project_id);
    }

    protected static function booted()
    {
        static::deleting(function ($file) {
            if ($file->type === self::TYPE_FILE && $file->path) {
                Storage::disk('public')->delete($file->path);
            }

            $file->knowledge()->each(function ($knowledge) {
                $knowledge->delete();
            });
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function knowledge(): HasMany
    {
        return $this->hasMany(AgentKnowledge::class, 'file_id');
    }

    public function isFullySummarized(): bool
    {
        return $this->knowledge()->count() > 0 && $this->knowledge()->whereNull('summary')->count() === 0;
    }

    protected static function newFactory(): AgentKnowledgeFileFactory
    {
        return AgentKnowledgeFileFactory::new();
    }
}
