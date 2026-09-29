<?php

namespace Agentoom\Core\Models\Traits;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasProjectOrUser
{
    public static function bootHasProjectOrUser(): void
    {
        static::saving(function ($model) {
            if (empty($model->user_id) && empty($model->project_id)) {
                if (auth()->check()) {
                    $model->user_id = auth()->id();

                    return;
                }

                if (app()->runningUnitTests()) {
                    return;
                }

                if (isset($model->system_agent) && $model->system_agent) {
                    return;
                }

                $superadmin = User::where('email', 'superadmin@unusualdope.com')->first();

                if ($superadmin) {
                    $model->user_id = $superadmin->id;

                    return;
                }

                throw new \Exception(__('agentoom-core::models.errors.project_or_user_required'));
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
