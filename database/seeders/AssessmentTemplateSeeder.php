<?php

namespace Database\Seeders;

use App\Models\AssessmentModule;
use App\Models\AssessmentTemplate;
use App\Models\AssessmentTemplateVersion;
use App\Models\HealthDomain;
use App\Services\TemplatePublishingService;
use Illuminate\Database\Seeder;

class AssessmentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $module = AssessmentModule::where('module_code', 'HIVAW')->first();
        $domainId = HealthDomain::where('domain_code', 'HIV')->value('health_domain_id');

        if (! $module || ! $domainId || $module->questions()->where('is_active', true)->doesntExist()) {
            return;
        }

        $template = AssessmentTemplate::firstOrCreate(
            ['template_code' => 'FOCUSED_HIV_COMMUNITY'],
            [
                'template_name' => 'HIV Awareness and Service Uptake',
                'description' => 'Focused assessment of HIV awareness, stigma, testing access, and service uptake.',
                'creation_path' => 'FOCUSED',
                'health_domain_id' => $domainId,
                'source_authority' => 'Vytte curated health assessment content',
                'license_code' => 'INTERNAL-CURATED',
            ]
        );

        $version = AssessmentTemplateVersion::firstOrCreate([
            'template_id' => $template->template_id,
            'version_number' => 1,
        ]);

        if ($version->status !== AssessmentTemplateVersion::STATUS_PUBLISHED) {
            $version->modules()->sync([
                $module->module_id => ['display_order' => 1, 'is_default' => true, 'area_label' => 'HIV'],
            ]);
            app(TemplatePublishingService::class)->publish($version);
        }
    }
}
