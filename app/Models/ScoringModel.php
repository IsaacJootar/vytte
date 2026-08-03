<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringModel extends Model
{
    use HasUuids;

    protected $primaryKey = 'scoring_model_id';

    protected $fillable = ['content_publisher_id', 'model_code', 'name', 'description', 'created_by'];

    public function contentPublisher(): BelongsTo
    {
        return $this->belongsTo(ContentPublisher::class, 'content_publisher_id', 'content_publisher_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ScoringModelVersion::class, 'scoring_model_id', 'scoring_model_id');
    }
}
