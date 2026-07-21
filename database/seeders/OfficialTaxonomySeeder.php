<?php

namespace Database\Seeders;

use App\Models\DomainDefinition;
use App\Models\DomainTaxonomy;
use App\Models\DomainTaxonomyVersion;
use App\Services\DomainTaxonomyPublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The official measurement-domain taxonomy.
 *
 * The seven-plus-one measurement domains scores roll up into, defined and published as one
 * governed taxonomy version. Extracted from the former demonstration seeder so the official
 * seed chain does not depend on any demonstration content.
 *
 * All eight WHO-aligned domains, including Financing, are defined and the version is
 * published, because frameworks map their indicators to a published domain definition and
 * cannot be composed against a draft.
 */
class OfficialTaxonomySeeder extends Seeder
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private static function definitions(): array
    {
        return [
            'GOV' => ['Governance and Accountability', 'Leadership, policy, oversight and accountability that let a facility run reliably. Recurs across departments without being owned by one.'],
            'WORK' => ['Workforce and Capability', 'Staff availability, skills, supervision and capacity to deliver the assessed service. Cuts across clinical and programme assessments.'],
            'SERV' => ['Service Delivery and Access', 'Availability, reach, continuity and accessibility of the assessed service. The stable lens for whether services are usable.'],
            'SAFE' => ['Safety and Quality', 'Practices that protect patients, staff and community and improve the reliability of care. Needs cross-department visibility.'],
            'RES' => ['Infrastructure, Equipment and Supplies', 'Physical infrastructure, equipment, commodities and utilities needed to deliver services. A common bottleneck across subjects.'],
            'INFO' => ['Information, Measurement and Learning', 'Records, reporting, data quality, data use and learning. Explains measurement and improvement capability.'],
            'PCOM' => ['Person-Centredness and Community Responsiveness', 'Respectful, understandable and community-aware service experience, interpreted through the normal assessment engine.'],
            'FIN' => ['Financing and Resource Management', 'Budgeting, financial protection, income and financial accountability. Completes the WHO health system building blocks so financing findings can roll up and be compared.'],
        ];
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $taxonomy = DomainTaxonomy::firstOrCreate(
                ['taxonomy_code' => 'VYTTE_HEALTH_ANALYTICAL_DOMAINS'],
                [
                    'taxonomy_name' => 'Vytte Health Measurement Domains',
                    'description' => 'The official measurement domains scores roll up into for cross-subject interpretation.',
                    'status' => 'ACTIVE',
                ]
            );

            // Idempotent: if a published version already covers all eight domains, leave it.
            $published = DomainTaxonomyVersion::where('domain_taxonomy_id', $taxonomy->domain_taxonomy_id)
                ->where('status', DomainTaxonomyVersion::STATUS_PUBLISHED)
                ->withCount('definitions')
                ->orderByDesc('version_number')
                ->first();

            if ($published && $published->definitions_count >= count(self::definitions())) {
                return;
            }

            $version = DomainTaxonomyVersion::firstOrCreate(
                ['domain_taxonomy_id' => $taxonomy->domain_taxonomy_id, 'version_number' => 1],
                [
                    'status' => DomainTaxonomyVersion::STATUS_DRAFT,
                    'methodology_notes' => 'Official Vytte measurement domains, aligned to the WHO health system building blocks.',
                ]
            );

            if ($version->status !== DomainTaxonomyVersion::STATUS_DRAFT) {
                return;
            }

            $order = 0;
            foreach (self::definitions() as $code => [$name, $definition]) {
                $domainId = DB::table('domains')->where('domain_code', $code)->value('domain_id');
                if (! $domainId) {
                    continue;
                }

                DomainDefinition::firstOrCreate(
                    ['domain_taxonomy_version_id' => $version->domain_taxonomy_version_id, 'domain_code' => $code],
                    [
                        'domain_id' => $domainId,
                        'domain_name' => $name,
                        'definition' => $definition,
                        'rationale' => 'Required so '.$name.' findings are visible across departments and subjects.',
                        'display_order' => ++$order,
                    ]
                );
            }

            app(DomainTaxonomyPublishingService::class)->publish($version->fresh());
        });
    }
}
