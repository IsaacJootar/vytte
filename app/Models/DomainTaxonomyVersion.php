<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomainTaxonomyVersion extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $primaryKey = 'domain_taxonomy_version_id';

    protected $fillable = [
        'domain_taxonomy_id',
        'version_number',
        'status',
        'methodology_notes',
        'rejected_candidates',
        'content_hash',
        'parent_version_id',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'rejected_candidates' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') === self::STATUS_PUBLISHED) {
                $allowed = ['status', 'updated_at'];
                $dirty = array_keys($version->getDirty());
                $disallowed = array_diff($dirty, $allowed);

                if ($disallowed !== []) {
                    throw new \LogicException('Published domain taxonomy versions are immutable. Publish a new version instead.');
                }

                if (! in_array($version->status, [self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED], true)) {
                    throw new \LogicException('Published domain taxonomy versions may only be superseded or archived.');
                }
            }
        });

        static::deleting(function (self $version): void {
            if ($version->status === self::STATUS_PUBLISHED) {
                throw new \LogicException('Published domain taxonomy versions cannot be deleted.');
            }
        });
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(DomainTaxonomy::class, 'domain_taxonomy_id', 'domain_taxonomy_id');
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(DomainDefinition::class, 'domain_taxonomy_version_id', 'domain_taxonomy_version_id')
            ->orderBy('display_order');
    }
}
