<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainDefinition;
use App\Models\DomainTaxonomy;
use App\Models\DomainTaxonomyVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DomainTaxonomyPublishingService
{
    /**
     * Opens a new draft version, carrying forward everything the current published
     * version defines and adding a stub for any measurement domain it does not.
     *
     * Copies rather than edits, because a published version is immutable. The copy is
     * what makes a measurement domain adoptable at all: without it, adding a domain to
     * the master list left it inert forever with no governed way to bring it into force.
     */
    public function startNewVersion(DomainTaxonomy $taxonomy, ?string $userId = null): DomainTaxonomyVersion
    {
        return DB::transaction(function () use ($taxonomy, $userId): DomainTaxonomyVersion {
            $existingDraft = $taxonomy->versions()
                ->where('status', DomainTaxonomyVersion::STATUS_DRAFT)
                ->first();

            if ($existingDraft) {
                throw ValidationException::withMessages([
                    'version' => 'This taxonomy already has a draft version. Publish or discard it before starting another.',
                ]);
            }

            $current = $taxonomy->versions()
                ->where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)
                ->orderByDesc('version_number')
                ->with('definitions')
                ->first();

            $draft = DomainTaxonomyVersion::create([
                'domain_taxonomy_id' => $taxonomy->domain_taxonomy_id,
                'version_number' => ((int) $taxonomy->versions()->max('version_number')) + 1,
                'status' => DomainTaxonomyVersion::STATUS_DRAFT,
                'methodology_notes' => $current?->methodology_notes,
                'rejected_candidates' => $current?->rejected_candidates ?? [],
                'parent_version_id' => $current?->domain_taxonomy_version_id,
            ]);

            $order = 0;

            foreach ($current?->definitions ?? collect() as $definition) {
                DomainDefinition::create([
                    'domain_taxonomy_version_id' => $draft->domain_taxonomy_version_id,
                    'domain_id' => $definition->domain_id,
                    'domain_code' => $definition->domain_code,
                    'domain_name' => $definition->domain_name,
                    'definition' => $definition->definition,
                    'rationale' => $definition->rationale,
                    'display_order' => ++$order,
                ]);
            }

            // Anything on the master list the previous version never defined. Seeded with
            // the domain's own name so the draft is publishable immediately; an
            // administrator refines the wording before publishing.
            $defined = $draft->definitions()->pluck('domain_id');

            foreach (Domain::whereNotIn('domain_id', $defined)->orderBy('display_order')->get() as $domain) {
                DomainDefinition::create([
                    'domain_taxonomy_version_id' => $draft->domain_taxonomy_version_id,
                    'domain_id' => $domain->domain_id,
                    'domain_code' => $domain->domain_code,
                    'domain_name' => $domain->domain_name,
                    'definition' => $domain->domain_name.' as a cross-cutting dimension of assessed performance.',
                    'rationale' => 'Added so this measurement domain can carry scores rather than remaining inactive.',
                    'display_order' => ++$order,
                ]);
            }

            app(AuditService::class)->record(
                'domain.taxonomy.version_started',
                $draft->fresh(),
                newValues: [
                    'version_number' => $draft->version_number,
                    'copied_from' => $current?->version_number,
                    'definition_count' => $order,
                ],
                userId: $userId,
            );

            return $draft->fresh('definitions');
        });
    }

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

        // Every measurement domain on the master list must be defined here. Without this
        // a domain can sit in the list carrying no scores and reporting nothing, looking
        // active while being inert — which is how FIN was left after it was first added.
        $undefined = Domain::whereNotIn('domain_id', $version->definitions->pluck('domain_id'))
            ->pluck('domain_code');

        if ($undefined->isNotEmpty()) {
            $errors['definitions'][] = 'These measurement domains have no definition in this version and would carry no scores: '
                .$undefined->implode(', ').'. Define them, or remove them from the measurement domain list.';
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

        // Exactly one version is in force. Two published versions would leave new
        // indicator mappings free to point at either, and nothing would say which
        // taxonomy a fresh framework was measured against.
        $previous = DomainTaxonomyVersion::where('domain_taxonomy_id', $version->domain_taxonomy_id)
            ->where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)
            ->where('version_number', '<', $version->version_number)
            ->get();

        foreach ($previous as $old) {
            $this->supersede($old, $version->fresh(), $publisherId);
        }

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
