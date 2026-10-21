<?php

namespace Database\Seeders;

use App\Models\AssessmentCatalogueRelease;
use App\Models\AssessmentModule;
use App\Models\DepartmentFrameworkVersion;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\QuestionVersion;
use App\Services\CataloguePublishingService;
use App\Services\OfficialFrameworkComposer;
use App\Services\QuestionVersionPublishingService;
use App\Services\ScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menstrual Health & Dignity baseline — School and PHC site assessments.
 *
 * Two Focused templates completed by one focal person per site. They measure two different
 * things and keep them apart: PROVISION (how well girls' needs are met today) and READINESS
 * (whether the site could host a Dignity Bank). Every scored answer sits on one of three fixed
 * ladders (4-level 100/67/33/0, 3-level 100/50/0, yes/no 100/0) with equal weights; profile and
 * existing-programme questions are context only and never scored. Each scored section maps to
 * its own measurement domain so it becomes its own section score, and the report reads those
 * section scores by the roles declared in the release's `report` policy.
 *
 * "Don't know" on a scored question is not an answer option: the runner's own "I cannot give a
 * direct answer" states (Unknown, Not assessed…) are excluded from the score, and a scored
 * option with no weight would be rejected at publication.
 *
 * Must-have conditions are single-condition compound rules (see AssessmentLogicService), one per
 * condition, so the report can name each missing must-have separately. They label a site "not
 * ready to host"; they never change a section score.
 *
 * Same governed path as the digital readiness instrument: questions through
 * QuestionVersionPublishingService, frameworks through OfficialFrameworkComposer, releases
 * through CataloguePublishingService. Idempotent; published content is never edited in place.
 */
class MenstrualHealthDignityBaselineSeeder extends Seeder
{
    private const HEALTH_DOMAIN_CODE = 'MENSTRUAL_HEALTH_DIGNITY';

    private const SITES = [
        'school' => [
            'module' => 'MHDS',
            'prefix' => 'MHS',
            'noun' => 'school',
            'framework' => 'Menstrual Health & Dignity Assessment — School',
            'release' => 'VYTTE_MHD_SCHOOL_V2',
            'previous_release' => 'VYTTE_MHD_SCHOOL_V1',
            'respondent' => 'School focal person',
        ],
        'phc' => [
            'module' => 'MHDP',
            'prefix' => 'MHP',
            'noun' => 'PHC',
            'framework' => 'Menstrual Health & Dignity Assessment — PHC',
            'release' => 'VYTTE_MHD_PHC_V2',
            'previous_release' => 'VYTTE_MHD_PHC_V1',
            'respondent' => 'PHC focal person',
        ],
        'girls' => [
            'module' => 'MHDG',
            'prefix' => 'MHG',
            'noun' => 'school or PHC',
            // Not an assessment of its own any more: its questions are placed inside the School and PHC
            // assessments, answered by the girls and women themselves. The module and its questions stay
            // published as the source of those questions; the release it once had is retired.
            'framework' => 'Menstrual Health & Dignity Survey — Girls and Women',
            'previous_release' => 'VYTTE_MHD_GIRLS_V1',
            'questions_only' => true,
            'respondent' => 'Girls and women',
        ],
    ];

    public function run(): void
    {
        $this->seedHealthDomain();

        // The girls' questions are published first: the School and PHC assessments place them.
        foreach (['girls', 'school', 'phc'] as $site) {
            $config = self::SITES[$site];
            $this->seedModule($config);
            $this->publishQuestions($site, $config);

            if ($config['questions_only'] ?? false) {
                $this->retireRelease($config['previous_release']);

                continue;
            }

            $result = $this->composeFramework($site, $config);

            if ($result['status'] === 'published') {
                $this->command?->info("{$config['framework']}: {$result['placed']} questions placed.");
                $this->publishRelease($site, $config);
            } elseif ($result['status'] === 'already_published') {
                $this->publishRelease($site, $config);
            } else {
                $this->command?->warn("{$config['framework']}: {$result['status']}.");
            }

            if ($result['missing'] !== []) {
                $this->command?->warn('Question codes referenced but not found: '.implode(', ', $result['missing']));
            }
        }
    }

    private function seedHealthDomain(): void
    {
        DB::table('health_domains')->insertOrIgnore([
            'domain_code' => self::HEALTH_DOMAIN_CODE,
            'domain_name' => 'Menstrual Health & Dignity',
            'display_order' => (int) DB::table('health_domains')->max('display_order') + 1,
        ]);
    }

    private function seedModule(array $config): void
    {
        AssessmentModule::updateOrCreate(
            ['target_type_code' => 'HEALTH_FACILITY', 'module_code' => $config['module']],
            [
                'module_name' => $config['framework'],
                'primary_respondent' => $config['respondent'],
                'estimated_duration_minutes' => $config['module'] === 'MHDG' ? 8 : 15,
                'data_collection_methods' => 'Interview · Observation',
                'is_active' => true,
                'requires_consent' => $config['module'] === 'MHDG',
            ]
        );
    }

    private function publishQuestions(string $site, array $config): void
    {
        $publishing = app(QuestionVersionPublishingService::class);
        $types = QuestionType::pluck('type_id', 'type_code');
        $moduleId = AssessmentModule::where('module_code', $config['module'])
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->value('module_id');

        $published = 0;
        $skipped = 0;

        foreach (self::questions($site) as $definition) {
            $result = DB::transaction(fn () => $this->publishQuestion($definition, $moduleId, $types, $publishing, $config));
            $result ? $published++ : $skipped++;
        }

        $this->command?->info("{$config['framework']} questions: {$published} published, {$skipped} skipped.");
    }

    private function publishQuestion(array $definition, int $moduleId, $types, QuestionVersionPublishingService $publishing, array $config): bool
    {
        $typeId = $types[$definition['type']] ?? null;

        if (! $typeId) {
            return false;
        }

        $question = Question::where('question_code', $definition['code'])->first();

        if ($question && $question->versions()->where('status', QuestionVersion::STATUS_PUBLISHED)->exists()) {
            return false;
        }

        if (! $question) {
            $question = Question::create([
                'module_id' => $moduleId,
                'question_number' => ((int) Question::where('module_id', $moduleId)->max('question_number')) + 1,
                'question_code' => $definition['code'],
                'question_text' => $definition['text'],
                'type_id' => $typeId,
                'respondent_role_hint' => $config['respondent'],
                'display_order' => ((int) Question::where('module_id', $moduleId)->max('display_order')) + 1,
                'is_active' => true,
                'is_scored' => $definition['scored'],
                'source' => 'VYTTE_OFFICIAL',
                'question_status' => 'DRAFT',
                'standard_alignment_status' => 'VYTTE_METHODOLOGY',
            ]);
        }

        $version = QuestionVersion::create([
            'question_id' => $question->question_id,
            'version_number' => ((int) $question->versions()->max('version_number')) + 1,
            'status' => QuestionVersion::STATUS_APPROVED,
            'question_text' => $definition['text'],
            'type_id' => $typeId,
            'options' => $this->optionPayload($definition),
            'numeric_config' => $definition['type'] === 'NUMERIC' ? ['min' => 0, 'unit' => $definition['unit'] ?? null] : null,
            'respondent_role_hint' => $config['respondent'],
            'methodology_notes' => $definition['note'] ?? null,
            'source_summary' => 'Authored by Vytte for the Menstrual Health & Dignity programme baseline (Oct 2026).',
        ]);

        $publishing->publish($version);

        return true;
    }

    private function optionPayload(array $definition): ?array
    {
        if (! isset($definition['options'])) {
            return null;
        }

        $payload = [];
        foreach ($definition['options'] as $index => [$label, $score]) {
            $payload[] = [
                'option_id' => $index + 1,
                'option_label' => $label,
                'option_order' => $index + 1,
                'score_weight' => $score,
                'critical_failure' => false,
            ];
        }

        return $payload;
    }

    private function composeFramework(string $site, array $config): array
    {
        $sections = [];
        foreach (array_merge(self::sections($site), self::personalSections()) as $section) {
            $sections[] = [
                'domain' => $section['domain'],
                'name' => $section['name'],
                'instructions' => $section['instructions'],
                'questions' => array_map(
                    fn ($q) => $q['scored'] ? $q['code'] : ['code' => $q['code'], 'scored' => false],
                    $section['questions']
                ),
            ];
        }

        return app(OfficialFrameworkComposer::class)->compose([
            'module' => $config['module'],
            'code' => $config['module'],
            'name' => $config['framework'],
            'description' => $site === 'girls'
                ? 'Short anonymous survey of girls and women about their own menstrual health: products, privacy, support, taking part and knowledge. One link per school or PHC.'
                : "Baseline assessment of menstrual health provision and readiness to host a Dignity Bank at a {$config['noun']}, completed by one focal person.",
            'type' => 'FOCUSED',
            'sections' => $sections,
        ]);
    }

    private function publishRelease(string $site, array $config): void
    {
        if (AssessmentCatalogueRelease::where('release_code', $config['release'])->exists()) {
            return;
        }

        $framework = DepartmentFrameworkVersion::where('display_name', $config['framework'])
            ->where('status', DepartmentFrameworkVersion::STATUS_PUBLISHED)
            ->first();

        if (! $framework) {
            $this->command?->warn("{$config['framework']}: release skipped, framework not published.");

            return;
        }

        $domainId = DB::table('health_domains')->where('domain_code', self::HEALTH_DOMAIN_CODE)->value('health_domain_id');

        DB::transaction(function () use ($site, $config, $framework, $domainId): void {
            $previous = AssessmentCatalogueRelease::where('release_code', $config['previous_release'] ?? '')->first();

            $release = AssessmentCatalogueRelease::create([
                'parent_release_id' => $previous?->catalogue_release_id,
                'release_code' => $config['release'],
                'release_name' => $config['framework'],
                'description' => $site === 'girls'
                    ? 'Anonymous survey of girls and women at one school or PHC, measured again at endline.'
                    : "Menstrual health provision and Dignity Bank readiness at a {$config['noun']}.",
                'creation_path' => 'FOCUSED',
                'health_domain_id' => $domainId,
                'aggregation_policy' => $this->aggregationPolicy($site, $config),
                'composition_rules' => ['latest_resolution' => 'forbidden'],
                // Anyone may answer, one person or several; their answers are combined into one result.
                // A school or PHC is reported from a single answerer if that is all it has; the girls'
                // assessment needs enough people for its percentages to mean something.
                'collection_config' => [
                    'allows_multi_respondent' => true,
                    'minimum_completed_respondents' => $site === 'girls' ? 10 : 1,
                    'aggregation_method' => 'ARITHMETIC_MEAN',
                    'scoring_profile_version' => ScoringService::ALGORITHM_VERSION,
                ],
            ]);

            $release->departmentFrameworkVersions()->syncWithoutDetaching([
                $framework->framework_version_id => [
                    'module_id' => $framework->module_id,
                    'applicability' => 'REQUIRED',
                    'display_order' => 1,
                    'area_label' => $config['framework'],
                ],
            ]);

            app(CataloguePublishingService::class)->publish($release->fresh());
            $this->retireRelease($config['previous_release'] ?? null);
        });

        $this->command?->info("{$config['release']} published.");
    }

    /** Retire a first-version release once its replacement is published. Nobody can start it any more. */
    private function retireRelease(?string $code): void
    {
        $release = $code ? AssessmentCatalogueRelease::where('release_code', $code)->first() : null;

        if ($release && $release->status === AssessmentCatalogueRelease::STATUS_PUBLISHED) {
            $release->update(['status' => AssessmentCatalogueRelease::STATUS_SUPERSEDED]);
            $this->command?->info("{$code} retired.");
        }
    }

    private function aggregationPolicy(string $site, array $config): array
    {
        return [
            'method' => 'MEAN_OF_SCORED_SUB_INDICES',
            // Enabled so the must-have rules can fire. No per-answer threshold and no flagged
            // options: only the named must-have rules ever label a site "not ready".
            'critical_failures' => ['enabled' => true],
            'compound_critical_rules' => $this->mustHaveRules($site, $config),
            'report' => [
                'type' => 'PROGRAMME',
                'programme' => 'Menstrual Health & Dignity Bank baseline',
                'site_noun' => $config['noun'],
                'roles' => ['RES' => 'PROVISION', 'SERV' => 'PROVISION', 'SAFE' => 'PROVISION', 'GOV' => 'READINESS'],
                'role_labels' => [
                    'PROVISION' => "How well girls' needs are met today",
                    'READINESS' => 'Ready to host a Dignity Bank',
                ],
                'thresholds' => ['provision_good_at_or_above' => 60, 'readiness_ready_at_or_above' => 60],
                'classification' => [
                    'HIGH_NEED_READY' => ['label' => 'Start here', 'detail' => 'Girls here have a high need and the '.$config['noun'].' is ready to host a Dignity Bank.'],
                    'HIGH_NEED_NOT_READY' => ['label' => 'Prepare first', 'detail' => 'Girls here have a high need, but the '.$config['noun'].' must fix its must-haves before a Dignity Bank can start.'],
                    'LOWER_NEED_READY' => ['label' => 'Later', 'detail' => 'Needs are lower here and the '.$config['noun'].' is ready. A possible later site.'],
                    'LOWER_NEED_NOT_READY' => ['label' => 'Not a priority now', 'detail' => 'Needs are lower here and the '.$config['noun'].' is not yet ready.'],
                ],
                'gap_count' => 5,
                'findings' => self::findings($site),
                'profile' => self::profileLines($site),
                // What the girls and women themselves report, taken from the same assessment.
                'min_respondents_to_report' => 10,
                'indicators' => self::indicators(),
            ],
        ];
    }

    /**
     * One plain sentence per scored question, written once and frozen with the template, so a report
     * reads as findings rather than as the question and the answer given. `gap` is shown when the
     * answer was weak, `good` when it was the best one. Keyed by question code.
     *
     * @return array<string, array{gap: string, good: string}>
     */
    private static function findings(string $site): array
    {
        $school = $site === 'school';
        $p = self::SITES[$site]['prefix'];
        $place = $school ? 'school' : 'PHC';
        $girls = $school ? 'girls' : 'girls and women';
        $toilet = $school ? "The girls' toilet" : 'The toilet for female clients';
        $leaders = $school ? 'The head teacher and school leadership' : 'The PHC in-charge and management';

        $rows = [
            'B1' => ['Menstrual products are not reliably available for emergencies.', 'Menstrual products are regularly available.'],
            'B3' => ["There is no clear, working way for {$girls} to get an emergency product.", "{$girls} can get an emergency product through a clear, working way."],
            'B4' => ['There is no reliable way to restock products when they run out.', 'Products can be restocked reliably when they run out.'],
            'B5' => ['Products have run out when they were needed.', 'Products have not run out when they were needed.'],
            'C1' => ['Menstrual health '.($school ? 'education' : 'information and counselling').' is not given regularly.', 'Menstrual health '.($school ? 'education' : 'information and counselling').' is given routinely.'],
            'C3' => ['Menstrual health materials are missing or rarely used.', 'Menstrual health materials are available and routinely used.'],
            'C4' => ["No trained staff member is at the {$place} to support menstrual health.", "A trained staff member is at the {$place} now."],
            'D1' => [$school ? 'There is no toilet used only by girls.' : 'There is no private toilet for female clients.', $school ? 'There is a toilet used only by girls.' : 'There is a private toilet for female clients.'],
            'D2' => ["{$toilet} is not working properly.", "{$toilet} is working."],
            'D3' => ['The toilet door does not close and lock properly.', 'The toilet door closes and locks.'],
            'D4' => ['The toilet is not private enough to change menstrual materials.', 'The toilet is private enough to change menstrual materials.'],
            'D5' => ['There is no water at the toilet.', 'There is water at the toilet.'],
            'D6' => ['There is no soap at the handwashing point.', 'There is soap at the handwashing point.'],
            'D7' => ['There is no covered bin for used menstrual materials.', 'A covered bin for used menstrual materials is in place.'],
            'D8' => ['Used menstrual materials are not disposed of safely.', 'Used menstrual materials are disposed of safely.'],
            'D9' => ['The toilet is not kept clean.', 'The toilet is kept clean.'],
            'D10' => ['The toilet cannot be used by someone with limited mobility.', 'The toilet can be used by someone with limited mobility.'],
            'F1' => ["{$leaders} have not yet committed to supporting a Dignity Bank.", "{$leaders} would fully support a Dignity Bank."],
            'F2' => ['No suitable staff member has been found to run a Dignity Bank.', 'A willing staff member is ready to run a Dignity Bank.'],
            'F3' => ['There is no secure place to store menstrual products.', 'A secure place to store menstrual products is available.'],
            'F4' => ["There is no private place where {$girls} could collect products unnoticed.", "A private place for {$girls} to collect products is available."],
            'F5' => ["{$girls} could not reach the collection place whenever they need it.", "{$girls} can reach the collection place whenever they need it."],
            'F6' => ["The {$place} is not committed to keeping simple records of products given out.", "The {$place} is willing to keep simple records of products given out."],
            'F7' => ['There is no way to ask for more stock when it runs low.', 'There is a way to ask for more stock when it runs low.'],
            'F8' => ['There is no strong group in place to support a Dignity Bank.', 'A strong group is already active to support a Dignity Bank.'],
        ];

        if ($school) {
            $rows['C5'] = ['Girls have no named trusted adult they can go to about menstrual concerns.', 'Girls know a trusted adult they can go to privately.'];
        } else {
            $rows['C5'] = ['Girls and women cannot reliably get help here for menstrual pain or problems.', 'Girls and women can routinely get help here for menstrual pain or problems.'];
            $rows['C6'] = ['Adolescent girls cannot get menstrual health support confidentially.', 'Adolescent girls can get menstrual health support confidentially.'];
            $rows['C7'] = ['There is no working referral route for problems the PHC cannot handle.', 'A working referral route exists for problems the PHC cannot handle.'];
        }

        $out = [];
        foreach ($rows as $id => [$gap, $good]) {
            $out["{$p}.{$id}"] = ['gap' => ucfirst($gap), 'good' => ucfirst($good)];
        }

        return $out;
    }

    /**
     * How the profile answers read in a report: one short line each, built from the answers (by
     * question id in braces) and left out when any of them was not answered.
     *
     * @return array<int, string>
     */
    private static function profileLines(string $site): array
    {
        $p = self::SITES[$site]['prefix'];
        $lines = $site === 'school'
            ? ['{A1}', '{A2} · {A3}', 'Boarding students: {A4}', 'Serves: {A5}',
                'Existing menstrual health activity: {E1}', 'It includes: {E2}', 'Supported by: {E3}',
                'Products now supplied by another programme: {E4}']
            : ['{A1}', 'Serves about {A2}', 'Services: {A3}', 'Also serves: {A4}',
                'Existing menstrual health activity: {E1}', 'It includes: {E2}', 'Supported by: {E3}',
                'Products now supplied by another programme: {E4}'];

        return array_map(fn ($line) => preg_replace('/\{(\w+)\}/', '{'.$p.'.$1}', $line), $lines);
    }
    /**
     * One single-condition rule per must-have, so each missing must-have is named on its own.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mustHaveRules(string $site, array $config): array
    {
        $noun = $config['noun'];
        $prefix = $config['prefix'];

        $specs = $site === 'school'
            ? [
                ['D1', 'No', 'There is no toilet used only by girls.'],
                ['F1', 'No', 'The head teacher and school leadership would not support a Dignity Bank.'],
                ['F2', 'No suitable person', 'No suitable staff member has been found to run a Dignity Bank.'],
                ['F3', 'No', 'There is no secure place to store menstrual products.'],
            ]
            : [
                ['D1', 'No', 'There is no private toilet that female clients can use.'],
                ['F1', 'No', 'The PHC in-charge and management would not support a Dignity Bank.'],
                ['F2', 'No suitable person', 'No suitable staff member has been found to run a Dignity Bank.'],
                ['F3', 'No', 'There is no secure place to store menstrual products.'],
            ];

        $definitions = collect(self::questions($site))->keyBy('code');
        $questionIds = Question::whereIn('question_code', collect($specs)->map(fn ($s) => $prefix.'.'.$s[0])->all())
            ->pluck('question_id', 'question_code');

        $rules = [];
        foreach ($specs as [$id, $worstLabel, $label]) {
            $code = $prefix.'.'.$id;
            $options = $definitions[$code]['options'];
            $position = collect($options)->search(fn ($option) => $option[0] === $worstLabel);

            if ($position === false || ! isset($questionIds[$code])) {
                continue;
            }

            $rules[] = [
                'version' => 1,
                'type' => 'response_rule',
                'operator' => 'ANY',
                'label' => $label,
                'conditions' => [[
                    'source_question_id' => (string) $questionIds[$code],
                    'comparison' => 'OPTION_SELECTED',
                    'value' => $position + 1,
                ]],
            ];
        }

        return $rules;
    }

    /* ---------------------------------------------------------------- content */

    private static function l4(string $a, string $b, string $c, string $d): array
    {
        return [[$a, 100], [$b, 66.67], [$c, 33.33], [$d, 0]];
    }

    private static function l3(string $a, string $b, string $c): array
    {
        return [[$a, 100], [$b, 50], [$c, 0]];
    }

    private static function l2(string $a, string $b): array
    {
        return [[$a, 100], [$b, 0]];
    }

    /** @param  array<int, string>  $labels */
    private static function ctx(array $labels): array
    {
        return array_map(fn ($label) => [$label, null], $labels);
    }

    private static function q(string $prefix, string $id, string $text, string $type, ?array $options, bool $scored = true, array $extra = []): array
    {
        return array_merge([
            'code' => $prefix.'.'.$id,
            'text' => $text,
            'type' => $type,
            'options' => $options,
            'scored' => $scored,
        ], $extra);
    }

    /** @return array<int, array{domain: string, name: string, instructions: ?string, questions: array<int, array<string, mixed>>}> */
    private static function sections(string $site): array
    {
        if ($site === 'girls') {
            return self::girlsSections();
        }

        $p = self::SITES[$site]['prefix'];
        $school = $site === 'school';
        $noun = self::SITES[$site]['noun'];
        $girls = $school ? 'girls' : 'girls and women';
        $vulnerable = self::ctx(['Low-income communities', 'Informal settlements', 'Displaced or conflict-affected families', 'Hard-to-reach communities', 'Girls with disabilities', 'Other vulnerable groups', 'None of these', "Don't know"]);
        $products = self::ctx(['Disposable pads', 'Reusable pads', 'Other reusable products (e.g. cups)', 'Other', 'None']);
        $progInclude = self::ctx(['Product distribution', 'Menstrual health education', 'WASH improvement', 'Pad bank', 'Reusable-product programme', 'Adolescent health programme', 'Other']);
        $progWho = self::ctx(['Government', 'NGO', 'UN / development partner', 'Private sector', 'Community', 'Other']);

        $profile = $school
            ? [
                self::q($p, 'A1', 'What type of school is this?', 'SINGLE_SELECT', self::ctx(['Public primary school', 'Public junior secondary school', 'Public senior secondary school', 'Public combined school', 'Other']), false),
                self::q($p, 'A2', 'About how many students does the school have in total?', 'NUMERIC', null, false, ['unit' => 'students']),
                self::q($p, 'A3', 'About how many of them are girls?', 'NUMERIC', null, false, ['unit' => 'girls']),
                self::q($p, 'A4', 'Does the school have boarding students?', 'SINGLE_SELECT', self::ctx(['Yes', 'No']), false),
                self::q($p, 'A5', 'Does the school serve any of these groups? (select all that apply)', 'MULTI_SELECT', $vulnerable, false),
            ]
            : [
                self::q($p, 'A1', 'What type of facility is this?', 'SINGLE_SELECT', self::ctx(['Primary health centre (PHC)', 'Comprehensive PHC', 'Other']), false),
                self::q($p, 'A2', 'About how many people does the PHC serve (catchment population)?', 'NUMERIC', null, false, ['unit' => 'people']),
                self::q($p, 'A3', 'Which of these services does the PHC provide? (select all that apply)', 'MULTI_SELECT', self::ctx(['Antenatal care', 'Family planning', 'Adolescent / youth-friendly services', 'Health education or counselling', 'None of these']), false),
                self::q($p, 'A4', 'Does the PHC serve any of these groups? (select all that apply)', 'MULTI_SELECT', $vulnerable, false),
            ];

        $access = [
            self::q($p, 'B1', $school
                ? 'Are menstrual products available at the school for emergencies? Ask to be shown them.'
                : 'Are menstrual products available at the PHC for girls and women who need them? Ask to be shown them.', 'SINGLE_SELECT', self::l4('Regularly available', 'Occasionally available', 'Rarely available', 'Not available')),
            self::q($p, 'B2', "Which menstrual products are available at the {$noun} now? (select all that apply)", 'MULTI_SELECT', $products, false),
            self::q($p, 'B3', $school
                ? 'Is there a clear way for a girl to get an emergency menstrual product at school?'
                : 'Is there a clear way for a girl or woman to get an emergency menstrual product at the PHC?', 'SINGLE_SELECT', self::l4('Yes, clear and works well', 'Yes, but informal', 'Exists but rarely works', 'No')),
            self::q($p, 'B4', 'Is there a way to restock products when they run out?', 'SINGLE_SELECT', self::l4('Yes, regular and reliable', 'Yes, but irregular', 'Only occasional donations', 'No way to restock')),
            self::q($p, 'B5', $school
                ? 'In the past school term, were there times when products were needed but not available?'
                : 'In the past 3 months, were there times when products were needed but not available?', 'SINGLE_SELECT', self::l4('Never', 'Occasionally', 'Frequently', 'Always (never available)')),
        ];

        $support = $school
            ? [
                self::q($p, 'C1', 'Is menstrual health education given at the school?', 'SINGLE_SELECT', self::l4('Yes, routinely', 'Occasionally', 'Only during special campaigns', 'No')),
                self::q($p, 'C2', 'Who gives it? (select all that apply)', 'MULTI_SELECT', self::ctx(['Class teacher', 'School counsellor', 'Health worker', 'NGO or partner', 'Peer educator', 'Other', 'No one']), false),
                self::q($p, 'C3', 'Are menstrual health teaching materials available?', 'SINGLE_SELECT', self::l3('Yes, and routinely used', 'Available but rarely used', 'Not available')),
                self::q($p, 'C4', 'Has any staff member at the school been trained on menstrual health?', 'SINGLE_SELECT', self::l3('Yes, a trained staff member is at the school now', 'Limited (training was brief, or the trained person has left)', 'No')),
                self::q($p, 'C5', 'Is there a trusted adult girls can go to privately about menstrual concerns, and do girls know who it is?', 'SINGLE_SELECT', self::l3('Yes, a named person girls know', 'Informal (girls go to whoever they trust)', 'No')),
            ]
            : [
                self::q($p, 'C1', 'Does the PHC give information or counselling on menstrual health?', 'SINGLE_SELECT', self::l4('Yes, routinely', 'Occasionally', 'Only when someone asks', 'No')),
                self::q($p, 'C2', 'Who gives it? (select all that apply)', 'MULTI_SELECT', self::ctx(['Nurse or midwife', 'Community health worker (CHEW / JCHEW / CHO)', 'Doctor', 'Health educator', 'NGO or partner', 'Other', 'No one']), false),
                self::q($p, 'C3', 'Are menstrual health information materials (leaflets, posters) available at the PHC?', 'SINGLE_SELECT', self::l3('Yes, and routinely used', 'Available but rarely used', 'Not available')),
                self::q($p, 'C4', 'Has any PHC staff member been trained in menstrual or adolescent health?', 'SINGLE_SELECT', self::l3('Yes, a trained staff member is at the PHC now', 'Limited (training was brief, or the trained person has left)', 'No')),
                self::q($p, 'C5', 'Can girls and women get help here for menstrual pain or problems?', 'SINGLE_SELECT', self::l4('Yes, routinely', 'Yes, but limited', 'Referral only', 'No')),
                self::q($p, 'C6', 'Can adolescent girls get menstrual health support confidentially?', 'SINGLE_SELECT', self::l3('Yes', 'Partly', 'No')),
                self::q($p, 'C7', 'Is there a working referral route for menstrual or reproductive health problems the PHC cannot handle?', 'SINGLE_SELECT', self::l4('Yes, and it works', 'Exists but weak', 'Informal only', 'No')),
            ];

        $toilet = [
            self::q($p, 'D1', $school
                ? 'Is there a toilet used only by girls?'
                : "Is there a private toilet female clients can use (separate from the men's, or a private single-use toilet)?", 'SINGLE_SELECT', self::l2('Yes', 'No')),
            self::q($p, 'D2', 'Is it working today?', 'SINGLE_SELECT', self::l3('Yes', 'Partly (usable but damaged)', 'No')),
            self::q($p, 'D3', 'Does the door close fully and lock from inside?', 'SINGLE_SELECT', self::l3('Closes and locks', 'Closes but does not lock', 'Does not close properly')),
            self::q($p, 'D4', 'Is it private enough to change menstrual materials (no gaps, no one can see in)?', 'SINGLE_SELECT', self::l3('Yes', 'Partly', 'No')),
            self::q($p, 'D5', 'Is there water at or near the toilet today?', 'SINGLE_SELECT', self::l3('Yes, at the toilet', 'Nearby, but not at the toilet', 'No water')),
            self::q($p, 'D6', 'Is there soap (or another hand-cleansing item) at the handwashing point today?', 'SINGLE_SELECT', self::l2('Yes', 'No')),
            self::q($p, 'D7', 'Is there a bin for used menstrual materials, and is it covered?', 'SINGLE_SELECT', self::l3('Covered bin present', 'Bin present but not covered', 'No bin')),
            self::q($p, 'D8', 'How are used menstrual materials disposed of?', 'SINGLE_SELECT', self::l3('Regularly and safely (e.g. collected, burned or buried)', 'Sometimes, or not fully safely', 'No system')),
            self::q($p, 'D9', 'Is the toilet clean today?', 'SINGLE_SELECT', self::l3('Clean', 'Fairly clean', 'Dirty')),
            self::q($p, 'D10', $school ? 'Can a girl with limited mobility use it?' : 'Can a woman or girl with limited mobility use it?', 'SINGLE_SELECT', self::l3('Yes', 'Partly', 'No')),
        ];

        $programmes = [
            self::q($p, 'E1', "Is there a menstrual health activity or programme at the {$noun} now?", 'SINGLE_SELECT', self::ctx(['Yes, active', 'Existed before but no longer active', 'No', "Don't know"]), false),
            self::q($p, 'E2', 'What does it include? (select all that apply)', 'MULTI_SELECT', $progInclude, false),
            self::q($p, 'E3', 'Who supports it? (select all that apply)', 'MULTI_SELECT', $progWho, false),
            self::q($p, 'E4', 'Are menstrual products now being supplied by another programme?', 'SINGLE_SELECT', self::ctx(['Yes, regularly', 'Yes, occasionally', 'No', "Don't know"]), false),
        ];

        $ready = [
            self::q($p, 'F1', $school
                ? 'If a Dignity Bank were set up here, would the head teacher and school leadership support it?'
                : 'If a Dignity Bank were set up here, would the PHC in-charge and management support it?', 'SINGLE_SELECT', self::l4('Yes, fully', 'Probably', 'Not sure', 'No')),
            self::q($p, 'F2', $school
                ? 'Is there a staff member who could look after a Dignity Bank?'
                : 'Is there a staff member who could look after a Dignity Bank (for example the adolescent or reproductive health focal person)?', 'SINGLE_SELECT', self::l4('Yes, someone in mind and willing', 'A likely person, not yet asked', 'No one identified yet', 'No suitable person')),
            self::q($p, 'F3', 'Is there a secure place to store menstrual products (a lockable cupboard or room)?', 'SINGLE_SELECT', self::l4('Yes, already', 'Could be provided', 'Limited or not very safe', 'No')),
            self::q($p, 'F4', "Is there a private place where {$girls} could collect products without being noticed?", 'SINGLE_SELECT', self::l4('Yes', 'Could be arranged', 'Difficult', 'No')),
            self::q($p, 'F5', $school
                ? 'Could girls reach that place whenever they need it during the school day, including break time?'
                : 'Could girls and women reach that place whenever the PHC is open?', 'SINGLE_SELECT', self::l4($school ? 'Yes, at all times' : 'Yes, whenever open', 'Most times', 'Only at limited times', 'No')),
            self::q($p, 'F6', "Would the {$noun} be willing to keep simple records of products given out (no names needed)?", 'SINGLE_SELECT', self::l3('Yes', 'Maybe', 'No')),
            self::q($p, 'F7', "If stock ran low, is there a way for the {$noun} to ask for more?", 'SINGLE_SELECT', self::l4('Yes, a way already exists', 'Could be set up', 'Only informally', 'No')),
            self::q($p, 'F8', $school
                ? 'Is there an existing group that could help support a Dignity Bank (for example a school health club, counselling team or parent-teacher committee)?'
                : "Is there an existing group that could help support a Dignity Bank (for example the adolescent health focal person, ward development committee, women's group or PHC management committee)?", 'SINGLE_SELECT', self::l4('Strong group already active', 'Some structure', 'Weak', 'None')),
        ];

        return [
            ['domain' => 'INFO', 'name' => 'Site profile', 'instructions' => "These answers describe the {$noun}. They are not scored.", 'questions' => $profile],
            ['domain' => 'RES', 'name' => 'Access to menstrual products', 'instructions' => null, 'questions' => $access],
            ['domain' => 'SERV', 'name' => $school ? 'Menstrual health education and support' : 'Menstrual health information, services and referral', 'instructions' => null, 'questions' => $support],
            ['domain' => 'SAFE', 'name' => $school ? "Girls' toilet, water and disposal" : "Female clients' toilet, water and disposal", 'instructions' => 'Go and look. Answer for the toilet '.($school ? 'girls' : 'female clients').' use most. Do not answer from memory.', 'questions' => $toilet],
            ['domain' => 'INFO', 'name' => 'Existing programmes', 'instructions' => 'These answers are not scored. They help avoid duplication and show who else is already working here.', 'questions' => $programmes],
            ['domain' => 'GOV', 'name' => 'Readiness to host a Dignity Bank', 'instructions' => 'A Dignity Bank is a private, stocked place in the '.$noun.' where '.$girls.' can get menstrual products when they need them, looked after by a trusted staff member. None is set up yet. These questions ask whether this '.$noun.' could do it.', 'questions' => $ready],
        ];
    }

    /**
     * The core outcome indicators a girls' and women's survey can measure, each tied to the
     * question(s) that answer it. `direction` says which way is better (UP or DOWN); `tests` list
     * the answers that count as the indicator happening, matched by the exact option label, and
     * `mode` says whether ALL or ANY of the tests must hold for one respondent. The KNOWLEDGE kind is
     * the average share of the knowledge questions a respondent got right. The three indicators that
     * measure the Dignity Bank itself are measured by the school or PHC assessment, not here.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function indicators(): array
    {
        return [
            ['code' => 'I1', 'outcome' => 'Reliable access to menstrual products', 'label' => 'Had enough menstrual materials during the last period', 'direction' => 'UP', 'mode' => 'ALL',
                'tests' => [['question' => 'MHG.B1', 'answers' => ['Yes, enough for the whole period']]]],
            ['code' => 'I2', 'outcome' => 'Reliable access to menstrual products', 'label' => 'Could get menstrual materials if their period came unexpectedly', 'direction' => 'UP', 'mode' => 'ANY',
                'tests' => [['question' => 'MHG.B2', 'answers' => ['Yes, easily', 'Yes, but it would be difficult']]]],
            ['code' => 'I3', 'outcome' => 'Reliable access to menstrual products', 'label' => 'Ran out of menstrual materials during the last period', 'direction' => 'DOWN', 'mode' => 'ANY',
                'tests' => [['question' => 'MHG.B3', 'answers' => ['Yes, I ran out']]]],
            ['code' => 'I4', 'outcome' => 'Managing menstruation safely and with dignity', 'label' => 'Have a private place to change when away from home', 'direction' => 'UP', 'mode' => 'ALL',
                'tests' => [['question' => 'MHG.C1', 'answers' => ['Yes, always']]]],
            ['code' => 'I5', 'outcome' => 'Managing menstruation safely and with dignity', 'label' => 'Have water, soap and a way to throw away used pads', 'direction' => 'UP', 'mode' => 'ALL',
                'tests' => [['question' => 'MHG.C2', 'answers' => ['Yes, always']], ['question' => 'MHG.C3', 'answers' => ['Yes, always']]]],
            ['code' => 'I6', 'outcome' => 'Managing menstruation safely and with dignity', 'label' => 'Face teasing, shame or restrictions because of their period', 'direction' => 'DOWN', 'mode' => 'ANY',
                'tests' => [['question' => 'MHG.D1', 'answers' => ['Yes, one or two things', 'Yes, many things']], ['question' => 'MHG.D2', 'answers' => ['Once or twice', 'Often']]]],
            ['code' => 'I7', 'outcome' => 'Knowledge and knowing where to get help', 'label' => 'Average menstrual health knowledge score', 'direction' => 'UP', 'kind' => 'KNOWLEDGE',
                'questions' => ['MHG.F1', 'MHG.F2', 'MHG.F3', 'MHG.F4', 'MHG.F5']],
            ['code' => 'I8', 'outcome' => 'Knowledge and knowing where to get help', 'label' => 'Know where to get help or information about periods', 'direction' => 'UP', 'mode' => 'ALL',
                'tests' => [['question' => 'MHG.F6', 'answers' => ['Yes, I can name a place or person']]]],
            ['code' => 'I9', 'outcome' => 'Taking part (secondary outcome)', 'label' => 'Missed school, work or an activity because of their period', 'direction' => 'DOWN', 'mode' => 'ANY',
                'tests' => [['question' => 'MHG.E1', 'answers' => ['Once or twice', 'Several times', 'Almost every period']], ['question' => 'MHG.E2', 'answers' => ['Sometimes', 'Often']]]],
        ];
    }

    /**
     * The girls' and women's own questions, placed after the facility questions in the School and PHC
     * assessments. Whoever answers (one person or several), these ask about her own experience. The
     * experience questions are scored in their own section and kept out of the school's or PHC's two
     * headline scores; the knowledge questions are not scored at all (a school's score should not be
     * a quiz result) and their correct answer is declared by the indicator, not by a score.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function personalSections(): array
    {
        $girls = self::girlsSections();
        $by = collect($girls)->keyBy('name');

        $experience = array_merge(
            $by['Pads and other materials']['questions'],
            $by['Privacy, water and disposal']['questions'],
            $by['Feeling respected and supported']['questions'],
        );
        $knowledge = array_map(fn ($q) => array_merge($q, ['scored' => false]), $by['What you know about periods']['questions']);

        return [
            ['domain' => 'INFO', 'name' => 'About you', 'instructions' => 'The next questions are for girls and women, about their own experience. Answer for yourself. Your answers are private and do not carry your name. If you are not sure of an answer in the earlier sections, ask someone who knows.', 'questions' => $by['About you']['questions']],
            ['domain' => 'PCOM', 'name' => 'Your own experience', 'instructions' => 'Pick the answer that is true for you. There are no right or wrong answers.', 'questions' => $experience],
            ['domain' => 'INFO', 'name' => 'What you know about periods', 'instructions' => 'These questions check what people know. Pick the answer you think is right. You can skip any you are not sure of.', 'questions' => $knowledge],
        ];
    }
    /** @return array<int, array{domain: string, name: string, instructions: ?string, questions: array<int, array<string, mixed>>}> */
    private static function girlsSections(): array
    {
        $p = 'MHG';

        $about = [
            self::q($p, 'A1', 'How old are you?', 'SINGLE_SELECT', self::ctx(['10 to 14 years', '15 to 19 years', '20 to 24 years', '25 to 49 years', '50 years or older']), false),
            self::q($p, 'A2', 'Which of these describes you best?', 'SINGLE_SELECT', self::ctx(['A girl in school', 'A girl not in school', 'A woman using this health centre', 'A woman from this community', 'Other']), false),
        ];

        $products = [
            self::q($p, 'B1', 'During your last period, did you have enough pads or other materials to use?', 'SINGLE_SELECT', self::l4('Yes, enough for the whole period', 'Enough most of the time', 'Not enough for part of the period', 'No, I did not have enough')),
            self::q($p, 'B2', 'If your period came unexpectedly today, could you get a pad or other material within a few hours?', 'SINGLE_SELECT', self::l3('Yes, easily', 'Yes, but it would be difficult', 'No, I could not get one')),
            self::q($p, 'B3', 'During your last period, did you run out of pads or other materials before it ended?', 'SINGLE_SELECT', self::l2('No, I did not run out', 'Yes, I ran out')),
            self::q($p, 'B4', 'What did you mainly use during your last period?', 'SINGLE_SELECT', self::ctx(['Disposable pads', 'Reusable pads', 'Cloth', 'Tissue or cotton wool', 'Something else']), false),
            self::q($p, 'B5', 'Where do you usually get them from? (select all that apply)', 'MULTI_SELECT', self::ctx(['My parents or family', 'I buy them myself', 'School', 'Health centre or clinic', 'Friends', 'A charity or programme', 'Somewhere else']), false),
        ];

        $privacy = [
            self::q($p, 'C1', 'When you are away from home (at school, work or a health centre), is there a private place where you can change your pad or cloth?', 'SINGLE_SELECT', self::l3('Yes, always', 'Sometimes', 'No')),
            self::q($p, 'C2', 'In the same places, is there water and soap to wash your hands and yourself?', 'SINGLE_SELECT', self::l3('Yes, always', 'Sometimes', 'No')),
            self::q($p, 'C3', 'In the same places, is there a bin or a safe way to throw away used pads?', 'SINGLE_SELECT', self::l3('Yes, always', 'Sometimes', 'No')),
        ];

        $dignity = [
            self::q($p, 'D1', 'Are there things you are not allowed to do, or feel unable to do, when you have your period?', 'SINGLE_SELECT', self::l3('No, nothing', 'Yes, one or two things', 'Yes, many things')),
            self::q($p, 'D2', 'Have you been teased, laughed at or shamed because of your period?', 'SINGLE_SELECT', self::l3('Never', 'Once or twice', 'Often')),
            self::q($p, 'D3', 'Would you feel comfortable asking a trusted adult, teacher or health worker for help with your period?', 'SINGLE_SELECT', self::l3('Yes', 'A little', 'No')),
        ];

        $participation = [
            self::q($p, 'E1', 'In the last 3 months, have you missed school, work or other normal activities because of your period?', 'SINGLE_SELECT', self::l4('Never', 'Once or twice', 'Several times', 'Almost every period')),
            self::q($p, 'E2', 'Have you stayed away from things like sports, class discussions or community events because of your period?', 'SINGLE_SELECT', self::l3('Never', 'Sometimes', 'Often')),
        ];

        $knowledge = [
            self::q($p, 'F1', 'What is a period?', 'SINGLE_SELECT', self::l3('A normal monthly bleeding that is part of growing up', 'A sign of illness or infection', 'Something caused by bad behaviour or a curse')),
            self::q($p, 'F2', 'Which of these is a normal period?', 'SINGLE_SELECT', self::l3('Bleeding that lasts about 2 to 7 days', 'Bleeding that lasts more than 10 days every month', 'Bleeding that never stops')),
            self::q($p, 'F3', 'How often should a pad be changed?', 'SINGLE_SELECT', self::l3('Every 4 to 6 hours', 'Once a day', 'Only when it is soaked through')),
            self::q($p, 'F4', 'Is a girl or woman unclean when she has her period?', 'SINGLE_SELECT', self::l3('No, a period is natural and she is not unclean', 'Only on the heaviest days', 'Yes, she is unclean until it ends')),
            self::q($p, 'F5', 'When should a girl or woman see a health worker about her period?', 'SINGLE_SELECT', self::l3('When bleeding is very heavy, very painful, or her periods stop', 'Never, all problems pass by themselves', 'Only if her mother says so')),
            self::q($p, 'F6', 'Do you know where to get help or information about periods?', 'SINGLE_SELECT', self::l3('Yes, I can name a place or person', 'I am not sure', 'No')),
        ];

        return [
            ['domain' => 'INFO', 'name' => 'About you', 'instructions' => 'This survey is for girls and women who have had a period. Your answers are private and do not carry your name. Pick the answer that is true for you. There are no right or wrong answers, except in the last section.', 'questions' => $about],
            ['domain' => 'RES', 'name' => 'Pads and other materials', 'instructions' => null, 'questions' => $products],
            ['domain' => 'SAFE', 'name' => 'Privacy, water and disposal', 'instructions' => null, 'questions' => $privacy],
            ['domain' => 'PCOM', 'name' => 'Feeling respected and supported', 'instructions' => null, 'questions' => array_merge($dignity, $participation)],
            ['domain' => 'SERV', 'name' => 'What you know about periods', 'instructions' => 'These questions check what people know. Pick the answer you think is right.', 'questions' => $knowledge],
        ];
    }
    /** @return array<int, array<string, mixed>> */
    private static function questions(string $site): array
    {
        return collect(self::sections($site))->flatMap(fn ($section) => $section['questions'])->values()->all();
    }
}
