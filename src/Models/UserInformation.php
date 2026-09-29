<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class UserInformation extends Model
{
    use Searchable;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'importance',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'importance' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'title' => (string) $this->title,
            'content' => (string) $this->content,
            'importance' => (int) $this->importance,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
