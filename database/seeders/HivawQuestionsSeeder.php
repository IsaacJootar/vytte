<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HivawQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $moduleId = DB::table('assessment_modules')
            ->where('target_type_code', 'COMMUNITY')
            ->where('module_code', 'HIVAW')
            ->value('module_id');

        if (! $moduleId) {
            return;
        }

        $cqDomainId = DB::table('domains')->where('domain_code', 'CQ')->value('domain_id');
        $opDomainId = DB::table('domains')->where('domain_code', 'OP')->value('domain_id');
        $singleSelectTypeId = DB::table('question_types')->where('type_code', 'SINGLE_SELECT')->value('type_id');
        $openEndedTypeId = DB::table('question_types')->where('type_code', 'OPEN_ENDED')->value('type_id');
        $unaidsStandardId = DB::table('standards_registry')->where('standard_code', 'UNAIDS_95_95_95')->value('standard_id');

        $d1Id = DB::table('module_domains')->insertGetId([
            'module_id' => $moduleId,
            'domain_number' => 1,
            'domain_label' => 'AWARENESS & KNOWLEDGE',
        ], 'module_domain_id');

        $d2Id = DB::table('module_domains')->insertGetId([
            'module_id' => $moduleId,
            'domain_number' => 2,
            'domain_label' => 'STIGMA & SOCIAL NORMS',
        ], 'module_domain_id');

        $d3Id = DB::table('module_domains')->insertGetId([
            'module_id' => $moduleId,
            'domain_number' => 3,
            'domain_label' => 'SERVICE ACCESS & UTILIZATION',
        ], 'module_domain_id');

        $chkiId = DB::table('sub_indices')->insertGetId([
            'module_id' => $moduleId,
            'domain_id' => $cqDomainId,
            'acronym' => 'CHKI',
            'full_name' => 'Community HIV Knowledge Index',
            'description' => 'Accuracy of community knowledge on HIV transmission and treatment (U=U).',
        ], 'sub_index_id');

        $chsiId = DB::table('sub_indices')->insertGetId([
            'module_id' => $moduleId,
            'domain_id' => $cqDomainId,
            'acronym' => 'CHSI',
            'full_name' => 'Community HIV Stigma Index',
            'description' => 'Level of stigma and discriminatory attitudes toward people living with HIV in the community.',
        ], 'sub_index_id');

        $htuiId = DB::table('sub_indices')->insertGetId([
            'module_id' => $moduleId,
            'domain_id' => $cqDomainId,
            'acronym' => 'HTUI',
            'full_name' => 'HIV Testing Uptake Index',
            'description' => 'Proportion of community members who have been tested and received their result.',
        ], 'sub_index_id');

        $htabId = DB::table('sub_indices')->insertGetId([
            'module_id' => $moduleId,
            'domain_id' => $opDomainId,
            'acronym' => 'HTAB',
            'full_name' => 'HIV Testing Access Barrier Index',
            'description' => 'Severity of the most commonly reported barrier to HIV testing access.',
        ], 'sub_index_id');

        $questions = [
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d1Id,
                'question_number' => 1,
                'question_code' => 'HIVAW.D1.Q1',
                'question_text' => 'Are you aware of where HIV testing services are available in your community?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 1,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => $unaidsStandardId,
                'standard_alignment_status' => 'ALIGNED_TO_STANDARD',
                'sub_index_acronym' => 'HTUI',
                'options' => [
                    ['option_label' => 'Yes, know exactly where', 'option_order' => 1, 'score_weight' => 100],
                    ['option_label' => 'Yes, but not sure of the exact location', 'option_order' => 2, 'score_weight' => 55],
                    ['option_label' => 'No, not aware', 'option_order' => 3, 'score_weight' => 0],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d1Id,
                'question_number' => 2,
                'question_code' => 'HIVAW.D1.Q2',
                'question_text' => 'How is HIV mainly transmitted?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 2,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => null,
                'standard_alignment_status' => 'NO_STANDARD_EXISTS',
                'sub_index_acronym' => 'CHKI',
                'options' => [
                    ['option_label' => 'Unprotected sex only', 'option_order' => 1, 'score_weight' => 30],
                    ['option_label' => 'Sharing needles only', 'option_order' => 2, 'score_weight' => 30],
                    ['option_label' => 'Mother-to-child during pregnancy, birth, or breastfeeding only', 'option_order' => 3, 'score_weight' => 30],
                    ['option_label' => 'All of the above', 'option_order' => 4, 'score_weight' => 100],
                    ['option_label' => 'Not sure', 'option_order' => 5, 'score_weight' => 0],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d1Id,
                'question_number' => 3,
                'question_code' => 'HIVAW.D1.Q3',
                'question_text' => 'Can a person living with HIV who takes treatment consistently still transmit the virus to a partner?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 3,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => $unaidsStandardId,
                'standard_alignment_status' => 'ALIGNED_TO_STANDARD',
                'sub_index_acronym' => 'CHKI',
                'options' => [
                    ['option_label' => 'Yes, always', 'option_order' => 1, 'score_weight' => 0],
                    ['option_label' => 'Sometimes', 'option_order' => 2, 'score_weight' => 30],
                    ['option_label' => 'No — risk is greatly reduced or eliminated with effective treatment (U=U)', 'option_order' => 3, 'score_weight' => 100],
                    ['option_label' => 'Not sure', 'option_order' => 4, 'score_weight' => 0],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d2Id,
                'question_number' => 1,
                'question_code' => 'HIVAW.D2.Q1',
                'question_text' => 'How would your community react if someone was known to be living with HIV?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 4,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => null,
                'standard_alignment_status' => 'NO_STANDARD_EXISTS',
                'sub_index_acronym' => 'CHSI',
                'options' => [
                    ['option_label' => 'Fully accepted, no different treatment', 'option_order' => 1, 'score_weight' => 100],
                    ['option_label' => 'Some gossip or judgment but no exclusion', 'option_order' => 2, 'score_weight' => 55],
                    ['option_label' => 'Significant discrimination or exclusion', 'option_order' => 3, 'score_weight' => 0],
                    ['option_label' => 'Not sure', 'option_order' => 4, 'score_weight' => 50],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d2Id,
                'question_number' => 2,
                'question_code' => 'HIVAW.D2.Q2',
                'question_text' => 'Would you be comfortable sharing a meal with someone known to be HIV-positive?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 5,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => null,
                'standard_alignment_status' => 'NO_STANDARD_EXISTS',
                'sub_index_acronym' => 'CHSI',
                'options' => [
                    ['option_label' => 'Yes, no concern', 'option_order' => 1, 'score_weight' => 100],
                    ['option_label' => 'Hesitant, but would still do it', 'option_order' => 2, 'score_weight' => 55],
                    ['option_label' => 'No, would avoid it', 'option_order' => 3, 'score_weight' => 0],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d2Id,
                'question_number' => 3,
                'question_code' => 'HIVAW.D2.Q3',
                'question_text' => 'Have you ever heard negative comments made about people living with HIV in your community?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 6,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => null,
                'standard_alignment_status' => 'NO_STANDARD_EXISTS',
                'sub_index_acronym' => 'CHSI',
                'options' => [
                    ['option_label' => 'Often', 'option_order' => 1, 'score_weight' => 0],
                    ['option_label' => 'Sometimes', 'option_order' => 2, 'score_weight' => 35],
                    ['option_label' => 'Rarely', 'option_order' => 3, 'score_weight' => 70],
                    ['option_label' => 'Never', 'option_order' => 4, 'score_weight' => 100],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d3Id,
                'question_number' => 1,
                'question_code' => 'HIVAW.D3.Q1',
                'question_text' => 'Have you or anyone in your household ever been tested for HIV?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 7,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => $unaidsStandardId,
                'standard_alignment_status' => 'ALIGNED_TO_STANDARD',
                'sub_index_acronym' => 'HTUI',
                'options' => [
                    ['option_label' => 'Yes, tested and know the result', 'option_order' => 1, 'score_weight' => 100],
                    ['option_label' => 'Yes, tested but never returned for the result', 'option_order' => 2, 'score_weight' => 40],
                    ['option_label' => 'No, never tested', 'option_order' => 3, 'score_weight' => 0],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d3Id,
                'question_number' => 2,
                'question_code' => 'HIVAW.D3.Q2',
                'question_text' => 'What is the biggest barrier to getting tested for HIV in your community?',
                'type_id' => $singleSelectTypeId,
                'display_order' => 8,
                'is_scored' => true,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => $unaidsStandardId,
                'standard_alignment_status' => 'ALIGNED_TO_STANDARD',
                'sub_index_acronym' => 'HTAB',
                'options' => [
                    ['option_label' => 'Fear of stigma if positive', 'option_order' => 1, 'score_weight' => 0],
                    ['option_label' => 'Distance to testing site', 'option_order' => 2, 'score_weight' => 0],
                    ['option_label' => 'Cost', 'option_order' => 3, 'score_weight' => 0],
                    ['option_label' => 'Lack of awareness of where to test', 'option_order' => 4, 'score_weight' => 0],
                    ['option_label' => 'No significant barrier', 'option_order' => 5, 'score_weight' => 100],
                ],
            ],
            [
                'question_id' => (string) Str::uuid(),
                'module_id' => $moduleId,
                'module_domain_id' => $d3Id,
                'question_number' => 3,
                'question_code' => 'HIVAW.D3.Q3',
                'question_text' => 'Additional comments about HIV awareness and services in your community (optional, not scored)',
                'type_id' => $openEndedTypeId,
                'display_order' => 9,
                'is_scored' => false,
                'source' => 'AI_GENERATED',
                'question_status' => 'APPROVED',
                'standard_reference_id' => null,
                'standard_alignment_status' => 'NO_STANDARD_EXISTS',
                'sub_index_acronym' => null,
                'options' => [],
            ],
        ];

        $subIndexMap = [
            'CHKI' => $chkiId,
            'CHSI' => $chsiId,
            'HTUI' => $htuiId,
            'HTAB' => $htabId,
        ];

        foreach ($questions as $q) {
            $options = $q['options'];
            $subIndexAcronym = $q['sub_index_acronym'];
            unset($q['options'], $q['sub_index_acronym']);

            $questionId = $q['question_id'];

            DB::table('questions')->insertOrIgnore([
                'question_id' => $questionId,
                'module_id' => $q['module_id'],
                'module_domain_id' => $q['module_domain_id'],
                'question_number' => $q['question_number'],
                'question_code' => $q['question_code'],
                'question_text' => $q['question_text'],
                'type_id' => $q['type_id'],
                'display_order' => $q['display_order'],
                'is_scored' => $q['is_scored'],
                'source' => $q['source'],
                'question_status' => $q['question_status'],
                'standard_reference_id' => $q['standard_reference_id'],
                'standard_alignment_status' => $q['standard_alignment_status'],
                'requires_observation' => false,
            ]);

            foreach ($options as $option) {
                DB::table('question_options')->insertOrIgnore([
                    'question_id' => $questionId,
                    'option_label' => $option['option_label'],
                    'option_order' => $option['option_order'],
                    'score_weight' => $option['score_weight'],
                ]);
            }

            if ($subIndexAcronym && isset($subIndexMap[$subIndexAcronym])) {
                DB::table('sub_index_questions')->insertOrIgnore([
                    'sub_index_id' => $subIndexMap[$subIndexAcronym],
                    'question_id' => $questionId,
                    'weight' => 1.000,
                ]);
            }
        }
    }
}
