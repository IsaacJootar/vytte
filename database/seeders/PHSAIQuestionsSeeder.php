<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PHSAIQuestionsSeeder extends Seeder
{
    // Map docx Type strings to question_types.type_code values
    private array $typeMap = [
        'single select' => 'SINGLE_SELECT',
        'multiple select' => 'MULTI_SELECT',
        'yes / no' => 'SINGLE_SELECT',
        'numeric' => 'NUMERIC',
        'likert scale' => 'LIKERT',
        'likert' => 'LIKERT',
        'rank' => 'RANKING',
        'long text' => 'OPEN_ENDED',
        'time estimate' => 'TIME_ESTIMATE',
    ];

    public function run(): void
    {
        $docxPath = 'C:/Users/HomePC/Downloads/PHSAI_Complete_Package_v1/PHSAI_Departmental_Questionnaires_v1_1.docx';

        if (! file_exists($docxPath)) {
            $this->command->warn("PHSAI docx not found at: {$docxPath}");

            return;
        }

        $lines = $this->extractLines($docxPath);
        $parsed = $this->parseModules($lines);

        // Preload type_id map
        $typeIds = DB::table('question_types')->pluck('type_id', 'type_code')->toArray();

        // Preload existing module codes → module_id
        $moduleIds = DB::table('assessment_modules')
            ->where('target_type_code', 'HEALTH_FACILITY')
            ->pluck('module_id', 'module_code')
            ->toArray();

        $totalQuestions = 0;

        foreach ($parsed as $modData) {
            $moduleCode = $modData['code'];

            if (! isset($moduleIds[$moduleCode])) {
                continue; // module not in DB yet — skip
            }

            $moduleId = $moduleIds[$moduleCode];

            // Skip if this module already has questions seeded
            if (DB::table('questions')->where('module_id', $moduleId)->exists()) {
                continue;
            }

            foreach ($modData['domains'] as $domainData) {
                $domainId = DB::table('module_domains')->insertGetId([
                    'module_id' => $moduleId,
                    'domain_number' => $domainData['number'],
                    'domain_label' => $domainData['label'],
                ]);

                foreach ($domainData['questions'] as $qOrder => $qData) {
                    $typeCode = $this->mapType($qData['type']);
                    $typeId = $typeIds[$typeCode] ?? $typeIds['SINGLE_SELECT'];
                    $isScored = $qData['scored'] && $domainData['number'] !== 1; // D1 is always respondent profile

                    $questionId = (string) Str::uuid();
                    DB::table('questions')->insert([
                        'question_id' => $questionId,
                        'module_id' => $moduleId,
                        'module_domain_id' => $domainId,
                        'question_number' => $qOrder + 1,
                        'question_code' => $qData['code'],
                        'question_text' => $qData['text'],
                        'type_id' => $typeId,
                        'display_order' => $qOrder + 1,
                        'is_active' => true,
                        'is_scored' => $isScored,
                        'source' => 'PHSAI_V1',
                        'question_status' => 'APPROVED',
                    ]);

                    // Yes/No questions get explicit binary weights (genuinely binary — always correct)
                    if (strtolower($qData['type']) === 'yes / no') {
                        DB::table('question_options')->insert([
                            ['question_id' => $questionId, 'option_label' => 'Yes', 'option_order' => 1, 'score_weight' => 1.0],
                            ['question_id' => $questionId, 'option_label' => 'No', 'option_order' => 2, 'score_weight' => 0.0],
                        ]);
                    } elseif (! empty($qData['options'])) {
                        // score_weight = null: requires human calibration per-question before scoring
                        $optionRows = [];
                        foreach ($qData['options'] as $oIdx => $optLabel) {
                            $optionRows[] = [
                                'question_id' => $questionId,
                                'option_label' => $optLabel,
                                'option_order' => $oIdx + 1,
                                'score_weight' => null,
                            ];
                        }
                        DB::table('question_options')->insert($optionRows);
                    }

                    $totalQuestions++;
                }
            }
        }

        $this->command->info("PHSAI questions seeded: {$totalQuestions} questions across ".count($parsed).' modules.');
    }

    private function extractLines(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $text = strip_tags(str_replace(
            ['</w:p>', '</w:tr>', '<w:br/>'],
            ["\n", "\n", "\n"],
            $xml
        ));

        $lines = explode("\n", $text);
        $cleaned = [];
        foreach ($lines as $line) {
            $line = trim(html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($line !== '') {
                $cleaned[] = $line;
            }
        }

        return $cleaned;
    }

    private function parseModules(array $lines): array
    {
        $modules = [];
        $current = null;
        $currentDomainIdx = -1;
        $skipUntilDepartment = false;
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Skip until next DEPARTMENT
            if ($skipUntilDepartment) {
                if (preg_match('/^DEPARTMENT\s+([A-Z]+)\s+·/', $line)) {
                    $skipUntilDepartment = false;
                } else {
                    continue;
                }
            }

            // Department header
            if (preg_match('/^DEPARTMENT\s+([A-Z]+)\s+·/', $line, $m)) {
                if ($current !== null) {
                    $modules[] = $current;
                }
                $current = ['code' => $m[1], 'domains' => []];
                $currentDomainIdx = -1;

                continue;
            }

            if ($current === null) {
                continue;
            }

            // Stop at OBSERVATION CHECKLIST or OUTPUTS GENERATED
            if (str_starts_with($line, 'OBSERVATION CHECKLIST') || str_starts_with($line, 'OUTPUTS GENERATED')) {
                $skipUntilDepartment = true;

                continue;
            }

            // Domain header
            if (preg_match('/^DOMAIN\s+(\d+)\s+·\s+(.+)/', $line, $m)) {
                $current['domains'][] = [
                    'number' => (int) $m[1],
                    'label' => trim($m[2]),
                    'questions' => [],
                ];
                $currentDomainIdx = count($current['domains']) - 1;

                continue;
            }

            if ($currentDomainIdx === -1) {
                continue;
            }

            // Question code line: MODULE.DN.QM
            if (preg_match('/^[A-Z]+\.D\d+\.Q\d+$/', $line)) {
                $code = $line;

                // question text
                $text = isset($lines[$i + 1]) ? $lines[$i + 1] : '';
                // skip if next line looks like a type, code, or section marker
                if (preg_match('/^(Type:|DOMAIN|DEPARTMENT|OBSERVATION|OUTPUTS)/', $text) || preg_match('/^[A-Z]+\.D\d+\.Q\d+$/', $text)) {
                    $text = '';
                } else {
                    $i++;
                }

                // type line
                $typeLine = isset($lines[$i + 1]) ? $lines[$i + 1] : '';
                $typeStr = '';
                if (preg_match('/^Type:\s*(.+)/', $typeLine, $tm)) {
                    $typeStr = trim($tm[1]);
                    // Normalise "Numeric (minutes)" → "Numeric"
                    $typeStr = preg_replace('/^(Numeric|Single Select|Multiple Select|Yes \/ No|Likert Scale|Likert|Rank|Long Text|Time Estimate).*/i', '$1', $typeStr);
                    $i++;
                }

                // options line — read if the next line isn't a code/domain/section marker and isn't a skip line
                $options = [];
                $nextLine = isset($lines[$i + 1]) ? $lines[$i + 1] : '';
                $isSkipLine = preg_match('/^(If yes|WHO recommends|DEPARTMENT|DOMAIN|OBSERVATION|OUTPUTS)/i', $nextLine)
                    || preg_match('/^[A-Z]+\.D\d+\.Q\d+$/', $nextLine)
                    || preg_match('/^Type:/', $nextLine);

                $hasOptions = in_array(strtolower($typeStr), ['single select', 'multiple select', 'likert scale', 'likert', 'rank']);

                if ($hasOptions && ! $isSkipLine && $nextLine !== '') {
                    $options = array_map('trim', explode(' · ', $nextLine));
                    // Sanity: if options look like a sentence (no · separator and > 80 chars), skip
                    if (count($options) === 1 && strlen($options[0]) > 80) {
                        $options = [];
                    } else {
                        $i++;
                    }
                }

                $isScored = stripos($text, 'not scored') === false;

                $current['domains'][$currentDomainIdx]['questions'][] = [
                    'code' => $code,
                    'text' => $text,
                    'type' => $typeStr,
                    'options' => $options,
                    'scored' => $isScored,
                ];
            }
        }

        // Flush last module
        if ($current !== null) {
            $modules[] = $current;
        }

        return $modules;
    }

    private function mapType(string $docxType): string
    {
        $key = strtolower(trim($docxType));

        return $this->typeMap[$key] ?? 'SINGLE_SELECT';
    }
}
