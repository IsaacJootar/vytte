<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAssistanceRun extends Model
{
    use HasUuids;

    protected $primaryKey = 'content_assistance_run_id';

    protected $fillable = [
        'framework_version_id', 'run_type', 'status', 'source_hash', 'findings', 'model', 'created_by',
    ];

    protected $casts = ['findings' => 'array'];

    public function frameworkVersion(): BelongsTo
    {
        return $this->belongsTo(DepartmentFrameworkVersion::class, 'framework_version_id', 'framework_version_id');
    }
}
