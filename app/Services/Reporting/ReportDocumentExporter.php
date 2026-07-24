<?php

namespace App\Services\Reporting;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Writer\PowerPoint2007;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * Renders one frozen report payload into office documents.
 *
 * Every format is a different view of the same payload — the same discipline as the on-screen
 * report and the PDF. Nothing here recomputes or reinterprets; it only lays out what the
 * engine already froze. That is what keeps a Word report, an Excel workbook, and a slide deck
 * telling the identical story.
 */
class ReportDocumentExporter
{
    private const DISCLAIMER = 'About this assessment: its questions draw on WHO and other public health frameworks. It is not a World Health Organization product, and its results are a management guide, not a clinical diagnosis or an official accreditation.';

    /**
     * A readable .docx report.
     *
     * @param  array<string, mixed>  $payload
     */
    public function word(array $payload): string
    {
        $word = new PhpWord;
        $word->addTitleStyle(1, ['bold' => true, 'size' => 18]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 13]);
        $section = $word->addSection();

        $section->addTitle($payload['title'] ?? 'Assessment Report', 1);
        $section->addText($this->targetLine($payload), ['color' => '666666']);

        $score = $payload['score'] ?? [];
        $section->addTextBreak();
        $section->addTitle('Overall Score', 2);
        $section->addText($this->overallLine($score), ['bold' => true, 'size' => 14]);

        $findings = collect($payload['intelligence']['findings'] ?? [])
            ->whereIn('category', ['CRITICAL_FINDING', 'WEAKNESS', 'STRENGTH']);
        if ($findings->isNotEmpty()) {
            $section->addTextBreak();
            $section->addTitle('What we found', 2);
            foreach ($findings as $finding) {
                $section->addListItem($finding['statement'], 0);
            }
        }

        $recommendations = collect($payload['intelligence']['recommendations'] ?? []);
        if ($recommendations->isNotEmpty()) {
            $section->addTextBreak();
            $section->addTitle('What to do next', 2);
            foreach ($recommendations as $rec) {
                $prefix = ($rec['horizon'] === 'IMMEDIATE' ? '[Do now] ' : '[Plan for] ');
                $section->addListItem($prefix.$rec['statement'], 0);
            }
        }

        $section->addTextBreak(2);
        $section->addText(self::DISCLAIMER, ['italic' => true, 'size' => 8, 'color' => '888888']);

        return $this->render(fn ($path) => (new Word2007($word))->save($path));
    }

    /**
     * A data-first .xlsx workbook: one sheet each for scores, domains, sub-indices,
     * findings, and recommendations.
     *
     * @param  array<string, mixed>  $payload
     */
    public function excel(array $payload): string
    {
        $book = new Spreadsheet;
        $score = $payload['score'] ?? [];

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

        $this->sheet($book, 'Findings', ['Category', 'Severity', 'Subject', 'Statement'],
            collect($payload['intelligence']['findings'] ?? [])->map(fn ($f) => [
                $f['category'] ?? '', $f['severity'] ?? '', $f['subject'] ?? '', $f['statement'] ?? '',
            ])->all());

        $this->sheet($book, 'Recommendations', ['Horizon', 'Type', 'Statement', 'From finding'],
            collect($payload['intelligence']['recommendations'] ?? [])->map(fn ($r) => [
                $r['horizon'] ?? '', $r['type'] ?? '', $r['statement'] ?? '', $r['from_finding']['statement'] ?? '',
            ])->all());

        return $this->render(fn ($path) => (new Xlsx($book))->save($path));
    }

    /**
     * A short .pptx deck: title, score, top findings, recommendations.
     *
     * @param  array<string, mixed>  $payload
     */
    public function powerpoint(array $payload): string
    {
        $deck = new PhpPresentation;
        $score = $payload['score'] ?? [];

        // Title slide (reuse the default first slide).
        $slide = $deck->getActiveSlide();
        $this->slideHeading($slide, $payload['title'] ?? 'Assessment Report', 34);
        $this->slideBody($slide, $this->targetLine($payload)."\n\n".$this->overallLine($score), 260);

        $findings = collect($payload['intelligence']['findings'] ?? [])
            ->whereIn('category', ['CRITICAL_FINDING', 'WEAKNESS', 'STRENGTH'])->take(6);
        if ($findings->isNotEmpty()) {
            $slide = $deck->createSlide();
            $this->slideHeading($slide, 'What we found', 26);
            $this->slideBody($slide, $findings->map(fn ($f) => '• '.$f['statement'])->implode("\n"), 320);
        }

        $recommendations = collect($payload['intelligence']['recommendations'] ?? [])->take(6);
        if ($recommendations->isNotEmpty()) {
            $slide = $deck->createSlide();
            $this->slideHeading($slide, 'What to do next', 26);
            $this->slideBody($slide, $recommendations->map(fn ($r) => '• '.$r['statement'])->implode("\n"), 320);
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
