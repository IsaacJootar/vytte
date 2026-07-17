<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('current.workspace')) {
            if (auth()->check() && ! auth()->user()->isPlatformAdmin()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $workspace = app('current.workspace');

        if (! $workspace) {
            return;
        }

        $column = method_exists($model, 'getWorkspaceForeignKey')
            ? $model->getWorkspaceForeignKey()
            : 'workspace_id';

        $builder->where($model->getTable().'.'.$column, $workspace->workspace_id);
    }
}
