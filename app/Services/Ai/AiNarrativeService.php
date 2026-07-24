<?php

namespace App\Services\Ai;

use App\Services\Reporting\ReportComposer;
use RuntimeException;

/**
 * Narrates the frozen structured intelligence — and is forbidden from inventing anything.
 *
 * This is the last layer and the only one that leaves the machine. It sits strictly on top
 * of the deterministic engine: it may rephrase, summarise, and prioritise findings a human
 * would otherwise read as a list, but it may not produce a finding, a score, or a
 * recommendation that the engine did not. The deterministic report stays the source of
 * truth; the narrative is a readable retelling of it (blueprint §10).
 *
 * The boundary is enforced two ways: the model is given ONLY the frozen structured output
 * (never the raw responses or free rein), and the system prompt states the rule explicitly.
 * If the integration is not configured, the whole feature is simply absent — the report does
 * not depend on it.
 */
class AiNarrativeService
{
    /** The rule, given to the model verbatim as its system prompt. */
    private const SYSTEM_PROMPT = <<<'PROMPT'
    You write the narrative summary of a health facility assessment for a management audience.

    You are given the assessment's already-computed findings, insights, and recommendations as
    structured data. Your job is only to turn that structure into clear, plain prose a
    non-technical manager can act on.

    Hard rules, without exception:
    - Never introduce a finding, score, number, strength, weakness, or recommendation that is
      not present in the data you were given. If it is not in the data, it does not exist.
    - Do not diagnose, do not predict the future, and do not give clinical or medical advice.
    - Every claim you make must trace to an item in the data.
    - Plain language. No jargon. Short sentences. A Nigerian business owner with no training
      should understand every line.
    - Do not invent facility names, people, dates, or context.

    Write 2 to 4 short paragraphs: what the assessment found, what matters most, and what to
    do next. Lead with the most serious item. Be honest about anything that could not be scored.
    PROMPT;

    public function __construct(private readonly AiChatClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Produce a narrative for a report payload, read through a lens.
     *
     * @param  array<string, mixed>  $payload  a frozen report snapshot payload
     * @return array{body: string, model: string, lens: string, source_hash: string}
     *
     * @throws RuntimeException when unavailable or the API fails — the caller degrades.
     */
    public function narrate(array $payload, string $lens = 'EXECUTIVE'): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('AI narrative is not configured.');
        }

        $intelligence = $payload['intelligence'] ?? [];
        $structured = $this->structuredInput($payload, $intelligence, $lens);

        $body = $this->client->message(
            system: self::SYSTEM_PROMPT,
            user: $structured,
            maxTokens: 1024,
        );

        return [
            'body' => $body,
            'model' => $this->client->model(),
            'lens' => $lens,
            // Ties the narrative to the exact intelligence it was written from, so a stale
            // narrative (regenerated after the report changed) is detectable.
            'source_hash' => hash('sha256', $structured),
        ];
    }

    /**
     * The only thing the model ever sees: the frozen structured intelligence, as compact
     * readable text. No raw responses, no database, nothing it could use to invent.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $intelligence
     */
    private function structuredInput(array $payload, array $intelligence, string $lens): string
    {
        $lensMeta = ReportComposer::lenses()[$lens] ?? ReportComposer::lenses()['EXECUTIVE'] ?? ['name' => 'Summary', 'question' => ''];
        $score = $payload['score'] ?? [];

        $lines = [];
        $lines[] = 'REPORT: '.($payload['title'] ?? 'Health assessment');
        $lines[] = 'LENS: '.$lensMeta['name'].' — '.$lensMeta['question'];
        $lines[] = 'OVERALL SCORE: '.($score['overall_score'] ?? 'not calibrated').' (calibration: '.($score['calibration_status'] ?? 'unknown').')';

        $findings = $intelligence['findings'] ?? [];
        $lines[] = "\nFINDINGS:";
        foreach ($findings as $finding) {
            $lines[] = '- ['.$finding['category'].'/'.$finding['severity'].'] '.$finding['statement'];
        }

        $recommendations = $intelligence['recommendations'] ?? [];
        if ($recommendations !== []) {
            $lines[] = "\nRECOMMENDATIONS (each cites the finding it came from):";
            foreach ($recommendations as $rec) {
                $lines[] = '- ('.$rec['horizon'].') '.$rec['statement'].' [from: '.($rec['from_finding']['statement'] ?? '').']';
            }
        }

        $lines[] = "\nWrite the narrative using only the items above.";

        return implode("\n", $lines);
    }
}
