<?php

namespace App\Services;

use App\Models\DomainTaxonomyVersion;
use Illuminate\Validation\ValidationException;

class DomainTaxonomyPublishingService
{
    public function publish(DomainTaxonomyVersion $version, ?string $publisherId = null): DomainTaxonomyVersion
    {
        $version->load(['taxonomy', 'definitions.domain']);
        $errors = [];

        if ($version->status !== DomainTaxonomyVersion::STATUS_DRAFT) {
            $errors['status'][] = 'Only draft domain taxonomy versions can be published.';
        }

        if ($version->definitions->isEmpty()) {
            $errors['definitions'][] = 'A domain taxonomy version must define at least one analytical domain.';
        }

        $duplicateDomains = $version->definitions->groupBy('domain_id')->first(fn ($items) => $items->count() > 1);
        if ($duplicateDomains) {
            $errors['definitions'][] = 'A taxonomy version cannot define the same domain more than once.';
        }

        $duplicateOrder = $version->definitions->groupBy('display_order')->first(fn ($items) => $items->count() > 1);
        if ($duplicateOrder) {
            $errors['definitions'][] = 'Domain display order values must be unique inside a taxonomy version.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $payload = [
            'taxonomy_code' => $version->taxonomy?->taxonomy_code,
            'taxonomy_name' => $version->taxonomy?->taxonomy_name,
            'version_number' => (int) $version->version_number,
            'methodology_notes' => $version->methodology_notes,
            'rejected_candidates' => $version->rejected_candidates ?? [],
            'domains' => $version->definitions
                ->sortBy('display_order')
                ->map(fn ($definition) => [
                    'domain_id' => (int) $definition->domain_id,
                    'domain_code' => $definition->domain_code,
                    'domain_name' => $definition->domain_name,
                    'definition' => $definition->definition,
                    'rationale' => $definition->rationale,
                    'display_order' => (int) $definition->display_order,
                ])->values()->all(),
        ];

        $version->update([
            'status' => DomainTaxonomyVersion::STATUS_PUBLISHED,
            'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'published_at' => now(),
            'published_by' => $publisherId,
        ]);

        app(AuditService::class)->record(
            'domain.taxonomy.published',
            $version->fresh(),
            ['status' => DomainTaxonomyVersion::STATUS_DRAFT],
            ['status' => DomainTaxonomyVersion::STATUS_PUBLISHED, 'content_hash' => $version->content_hash],
            userId: $publisherId,
        );

        return $version->fresh(['taxonomy', 'definitions']);
    }

    public function supersede(DomainTaxonomyVersion $version, DomainTaxonomyVersion $replacement, ?string $userId = null): DomainTaxonomyVersion
    {
        if ($version->status !== DomainTaxonomyVersion::STATUS_PUBLISHED || $replacement->status !== DomainTaxonomyVersion::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['status' => 'Only a published taxonomy version can supersede another published version.']);
        }

        $version->update(['status' => DomainTaxonomyVersion::STATUS_SUPERSEDED]);

        app(AuditService::class)->record(
            'domain.taxonomy.superseded',
            $version->fresh(),
            ['status' => DomainTaxonomyVersion::STATUS_PUBLISHED],
            ['status' => DomainTaxonomyVersion::STATUS_SUPERSEDED, 'replacement_version_id' => $replacement->domain_taxonomy_version_id],
            userId: $userId,
        );

        return $version->fresh();
    }
}
