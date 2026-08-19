<?php

namespace App\Services\Reporting;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Writer\PowerPoint2007;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings as WordSettings;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * Renders one frozen report payload into office documents.
 *
 * Every format is a different view of the same payload — the same discipline as the on-screen
 * report and the PDF. Nothing here recomputes or reinterprets; it only lays out what the
 * engine already froze. That is what keeps a Word report, an Excel workbook, and a slide deck
 * telling the identical story.
 *
 * The documents carry the full diagnostic — scores, findings, insights, root causes, risks and
 * recommendations — so a board member reading the file alone sees what the on-screen report
 * shows, without needing access to the system.
 */
class ReportDocumentExporter
{
    private const DISCLAIMER = 'About this assessment: its questions draw on WHO and other public health frameworks. It is not a World Health Organization product, and its results are a management guide, not a clinical diagnosis or an official accreditation.';

    public function __construct(private readonly InsightService $insights) {}

    /**
     * A readable .docx report carrying the full diagnostic.
     *
     * @param  array<string, mixed>  $payload
     */
    public function word(array $payload): string
    {
        // PhpWord does NOT escape special characters (&, <, >) by default, unlike the
        // spreadsheet and presentation writers. Without this an assessment titled, say,
        // "Infection Prevention & Control" produces a document Word refuses to open.
        WordSettings::setOutputEscapingEnabled(true);

        $word = new PhpWord;
        $word->addTitleStyle(1, ['bold' => true, 'size' => 18]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 13]);
        $word->addTitleStyle(3, ['bold' => true, 'size' => 11]);
        $section = $word->addSection();
        $intel = $payload['intelligence'] ?? [];

        $section->addTitle($payload['title'] ?? 'Assessment Report', 1);
        $section->addText($this->targetLine($payload), ['color' => '666666']);

        $score = $payload['score'] ?? [];
        $section->addTextBreak();
        $section->addTitle('Overall score', 2);
        $section->addText($this->overallLine($score), ['bold' => true, 'size' => 14]);
        if (($score['calibration_status'] ?? null) === 'CRITICAL_FAILURE') {
            $section->addText('A critical failure was recorded — see the critical finding below. The overall score above still reflects how the whole assessment performed.', ['color' => 'B91C1C']);
        }

        // Score breakdown by measurement domain, then sub-index.
        $domains = $payload['domain_scores'] ?? [];
        if ($domains !== []) {
            $section->addTextBreak();
            $section->addTitle('Scores by area', 2);
            foreach ($domains as $d) {
                $line = ($d['domain_name'] ?? 'Area').': '.$this->scoreText($d['score'] ?? null).' — '.$this->band($d['score'] ?? null);
                $section->addListItem($line, 0);
            }
        }

        $subIndices = $payload['sub_index_scores'] ?? [];
        if ($subIndices !== []) {
            $section->addTextBreak();
            $section->addTitle('Sub-index scores', 2);
            foreach ($subIndices as $s) {
                $name = ($s['full_name'] ?? $s['acronym'] ?? 'Sub-index');
                $section->addListItem($name.': '.$this->scoreText($s['score'] ?? null), 0);
            }
        }

        // What we found — findings with the reasoning and consequence, not just the headline.
        $findings = collect($intel['findings'] ?? []);
        if ($findings->isNotEmpty()) {
            $section->addTextBreak();
            $section->addTitle('What we found', 2);
            foreach ($findings as $finding) {
                $section->addListItem($finding['statement'] ?? '', 0, ['bold' => true]);
                if (! empty($finding['why'])) {
                    $section->addText('Why it matters: '.$finding['why'], ['size' => 9, 'color' => '555555']);
                }
                if (! empty($finding['consequence'])) {
                    $section->addText('If left unaddressed: '.$finding['consequence'], ['size' => 9, 'color' => '555555']);
                }
                if (! empty($finding['expected_impact'])) {
                    $section->addText('Potential to improve: '.ucfirst(strtolower($finding['expected_impact'])), ['size' => 9, 'color' => '555555']);
                }
            }
        }

        // Insights, grouped by the governed category they belong to.
        $insightGroups = $this->insights->groupedForDocument($intel['insights'] ?? []);
        if ($insightGroups !== []) {
            $section->addTextBreak();
            $section->addTitle('Insights', 2);
            foreach ($insightGroups as $group) {
                $section->addTitle($group['name'], 3);
                foreach ($group['rows'] as $row) {
                    $section->addListItem($row['statement'] ?? '', 1);
                }
            }
        }

        // Root causes — the systemic patterns behind the findings.
        $rootCauses = collect($intel['root_causes'] ?? []);
        if ($rootCauses->isNotEmpty()) {
            $section->addTextBreak();
            $section->addTitle('Likely root causes', 2);
            foreach ($rootCauses as $cause) {
                $section->addListItem($cause['statement'] ?? '', 0);
                foreach (($cause['contributing_indicators'] ?? []) as $indicator) {
                    if (! empty($indicator['question_text'])) {
                        $section->addListItem($indicator['question_text'], 1, ['size' => 9, 'color' => '666666']);
                    }
                }
            }
        }

        // Risks — what happens if nothing changes.
        $risks = collect($intel['risks'] ?? []);
        if ($risks->isNotEmpty()) {
            $section->addTextBreak();
            $section->addTitle('Risks if nothing changes', 2);
            foreach ($risks as $risk) {
                $label = '['.ucfirst(strtolower($risk['level'] ?? 'risk')).'] ';
                $section->addListItem($label.($risk['statement'] ?? ''), 0, ['bold' => true]);
                if (! empty($risk['consequence'])) {
                    $section->addText($risk['consequence'], ['size' => 9, 'color' => '555555']);
                }
            }
        }

        // What to do next.
        $recommendations = collect($intel['recommendations'] ?? []);
        if ($recommendations->isNotEmpty()) {
            $section->addTextBreak();
            $section->addTitle('What to do next', 2);
            foreach ($recommendations as $rec) {
                $prefix = (($rec['horizon'] ?? null) === 'IMMEDIATE' ? '[Do now] ' : '[Plan for] ');
                $section->addListItem($prefix.($rec['statement'] ?? ''), 0);
            }
        }

        $section->addTextBreak(2);
        $section->addText(self::DISCLAIMER, ['italic' => true, 'size' => 8, 'color' => '888888']);

        return $this->render(fn ($path) => (new Word2007($word))->save($path));
    }

    /**
     * A data-first .xlsx workbook: one sheet each for scores, domains, sub-indices,
     * findings, insights, root causes, risks and recommendations.
     *
     * @param  array<string, mixed>  $payload
     */
    public function excel(array $payload): string
    {
        $book = new Spreadsheet;
        $score = $payload['score'] ?? [];
        $intel = $payload['intelligence'] ?? [];

        $summary = $book->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->fromArray([
            ['Report', $payload['title'] ?? 'Assessment Report'],
            ['Target', $payload['target']['name'] ?? ''],
            ['Project', $payload['project']['name'] ?? ''],
            ['Completed', $payload['completed_at'] ?? ''],
            ['Overall score', $score['overall_score'] ?? 'Not calibrated'],
            ['Calibration', $score['calibration_status'] ?? ''],
            ['Maturity', $score['maturity_level']['name'] ?? ''],
        ], null, 'A1');

        $this->sheet($book, 'Domain Scores', ['Domain', 'Code', 'Score', 'Calibration'],
            collect($payload['domain_scores'] ?? [])->map(fn ($d) => [
                $d['domain_name'] ?? '', $d['domain_code'] ?? '', $d['score'] ?? '', $d['calibration_status'] ?? '',
            ])->all());

        $this->sheet($book, 'Sub-Index Scores', ['Sub-index', 'Full name', 'Domain', 'Score', 'Calibration'],
            collect($payload['sub_index_scores'] ?? [])->map(fn ($s) => [
                $s['acronym'] ?? '', $s['full_name'] ?? '', $s['domain_name'] ?? '', $s['score'] ?? '', $s['calibration_status'] ?? '',
            ])->all());

        $this->sheet($book, 'Findings', ['Category', 'Severity', 'Subject', 'Statement', 'Why', 'If unaddressed', 'Potential to improve'],
            collect($intel['findings'] ?? [])->map(fn ($f) => [
                $f['category'] ?? '', $f['severity'] ?? '', $f['subject'] ?? '', $f['statement'] ?? '',
                $f['why'] ?? '', $f['consequence'] ?? '', $f['expected_impact'] ?? '',
            ])->all());

        $this->sheet($book, 'Insights', ['Category', 'Polarity', 'Subject', 'Statement'],
            collect($this->insights->groupedForDocument($intel['insights'] ?? []))->flatMap(fn ($group) => collect($group['rows'])->map(fn ($r) => [
                $group['name'], $r['polarity'] ?? '', $r['subject'] ?? '', $r['statement'] ?? '',
            ]))->all());

        $this->sheet($book, 'Root Causes', ['Subject', 'Severity', 'Statement', 'Contributing items'],
            collect($intel['root_causes'] ?? [])->map(fn ($c) => [
                $c['subject'] ?? '', $c['severity'] ?? '', $c['statement'] ?? '',
                collect($c['contributing_indicators'] ?? [])->pluck('question_text')->filter()->implode(' | '),
            ])->all());

        $this->sheet($book, 'Risks', ['Subject', 'Likelihood', 'Impact', 'Level', 'Statement', 'Consequence'],
            collect($intel['risks'] ?? [])->map(fn ($r) => [
                $r['subject'] ?? '', $r['likelihood'] ?? '', $r['impact'] ?? '', $r['level'] ?? '', $r['statement'] ?? '', $r['consequence'] ?? '',
            ])->all());

        $this->sheet($book, 'Recommendations', ['Horizon', 'Type', 'Statement', 'From finding'],
            collect($intel['recommendations'] ?? [])->map(fn ($r) => [
                $r['horizon'] ?? '', $r['type'] ?? '', $r['statement'] ?? '', $r['from_finding']['statement'] ?? '',
            ])->all());

        return $this->render(fn ($path) => (new Xlsx($book))->save($path));
    }

    /**
     * A presentation .pptx deck: title, score, findings, risks, and recommendations —
     * enough for a board meeting without reading the full document.
     *
     * @param  array<string, mixed>  $payload
     */
    public function powerpoint(array $payload): string
    {
        $deck = new PhpPresentation;
        $score = $payload['score'] ?? [];
        $intel = $payload['intelligence'] ?? [];

        // Title slide (reuse the default first slide).
        $slide = $deck->getActiveSlide();
        $this->slideHeading($slide, $payload['title'] ?? 'Assessment Report', 34);
        $this->slideBody($slide, $this->targetLine($payload)."\n\n".$this->overallLine($score), 260);

        $findings = collect($intel['findings'] ?? [])
            ->whereIn('category', ['CRITICAL_FINDING', 'WEAKNESS', 'STRENGTH'])->take(6);
        if ($findings->isNotEmpty()) {
            $slide = $deck->createSlide();
            $this->slideHeading($slide, 'What we found', 26);
            $this->slideBody($slide, $findings->map(fn ($f) => '• '.($f['statement'] ?? ''))->implode("\n"), 380);
        }

        $risks = collect($intel['risks'] ?? [])->take(5);
        if ($risks->isNotEmpty()) {
            $slide = $deck->createSlide();
            $this->slideHeading($slide, 'Risks if nothing changes', 26);
            $this->slideBody($slide, $risks->map(fn ($r) => '• ['.ucfirst(strtolower($r['level'] ?? 'risk')).'] '.($r['statement'] ?? ''))->implode("\n"), 380);
        }

        $recommendations = collect($intel['recommendations'] ?? [])->take(6);
        if ($recommendations->isNotEmpty()) {
            $slide = $deck->createSlide();
            $this->slideHeading($slide, 'What to do next', 26);
            $this->slideBody($slide, $recommendations->map(fn ($r) => '• '.(($r['horizon'] ?? null) === 'IMMEDIATE' ? '[Do now] ' : '[Plan for] ').($r['statement'] ?? ''))->implode("\n"), 380);
        }

        return $this->render(fn ($path) => (new PowerPoint2007($deck))->save($path));
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function sheet(Spreadsheet $book, string $title, array $headers, array $rows): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(substr($title, 0, 31));
        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
    }

    private function slideHeading($slide, string $text, int $size): void
    {
        $shape = $slide->createRichTextShape()->setHeight(80)->setWidth(880)->setOffsetX(40)->setOffsetY(40);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $run = $shape->createTextRun($text);
        $run->getFont()->setBold(true)->setSize($size)->setColor(new Color('FF0F172A'));
    }

    private function slideBody($slide, string $text, int $height): void
    {
        $shape = $slide->createRichTextShape()->setHeight($height)->setWidth(880)->setOffsetX(40)->setOffsetY(140);
        foreach (explode("\n", $text) as $i => $line) {
            if ($i > 0) {
                $shape->createParagraph();
            }
            $shape->getActiveParagraph()->createTextRun($line)->getFont()->setSize(16)->setColor(new Color('FF334155'));
        }
    }

    private function targetLine(array $payload): string
    {
        $parts = array_filter([
            $payload['target']['name'] ?? null,
            $payload['project']['name'] ?? null,
            isset($payload['completed_at']) ? 'Completed '.substr((string) $payload['completed_at'], 0, 10) : null,
        ]);

        return implode(' · ', $parts);
    }

    private function overallLine(array $score): string
    {
        if (($score['overall_score'] ?? null) === null) {
            return 'Overall score: not yet calibrated';
        }

        return 'Overall score: '.round((float) $score['overall_score'], 1).' / 100'
            .($score['maturity_level']['name'] ?? null ? ' — '.$score['maturity_level']['name'] : '');
    }

    private function scoreText(?float $score): string
    {
        return $score === null ? 'not calibrated' : round((float) $score).' / 100';
    }

    private function band(?float $score): string
    {
        return match (true) {
            $score === null => 'Not calibrated',
            $score >= 70.0 => 'Strong',
            $score >= 45.0 => 'Moderate',
            default => 'Weak',
        };
    }

    /**
     * Write via a temp file (the PHPOffice writers target paths, not streams), then return
     * the bytes and clean up.
     */
    private function render(callable $save): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vytte_export_');
        try {
            $save($path);

            return (string) file_get_contents($path);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
