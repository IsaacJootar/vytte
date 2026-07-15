<?php

namespace App\Traits;

use App\Models\Workspace;
use App\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model) {
            $column = method_exists($model, 'getWorkspaceForeignKey')
                ? $model->getWorkspaceForeignKey()
                : 'workspace_id';

            if (empty($model->{$column})) {
                $workspace = app('current.workspace');
                if ($workspace) {
                    $model->{$column} = $workspace->workspace_id;
                }
            }
        });
    }

    public function workspace(): BelongsTo
    {
        $column = method_exists($this, 'getWorkspaceForeignKey')
            ? $this->getWorkspaceForeignKey()
            : 'workspace_id';

        return $this->belongsTo(Workspace::class, $column, 'workspace_id')
            ->withoutGlobalScope(WorkspaceScope::class);
    }
}
