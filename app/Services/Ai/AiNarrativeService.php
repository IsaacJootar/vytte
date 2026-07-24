<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Generates the AI report products — and is forbidden from inventing anything.
 *
 * This is the last layer and the only one that leaves the machine. It sits strictly on top of
 * the deterministic engine: it may rephrase, summarise, and prioritise the structured findings
 * a human would otherwise read as a list, but it may not produce a finding, a score, or a
 * recommendation that the engine did not. The deterministic report stays the source of truth;
 * each product is a readable retelling of it for a particular audience (blueprint §10).
 *
 * The boundary is enforced two ways: the model is given ONLY the frozen structured output
 * (never the raw responses or free rein), and the system prompt states the rule explicitly.
 * Products differ only in which slice of the structured intelligence they are handed and the
 * audience instruction appended — never in freedom to invent. If the integration is not
 * configured, the feature is simply absent; the report does not depend on it.
 */
class AiNarrativeService
{
    /** The rule, given to the model verbatim at the top of every product's system prompt. */
    private const BASE_RULES = <<<'PROMPT'
    You write summaries of a health facility assessment. You are given the assessment's
    already-computed findings, insights, recommendations, root causes and risks as structured
    data. Your job is only to turn that structure into clear, plain prose.

    Hard rules, without exception:
    - Never introduce a finding, score, number, strength, weakness, recommendation, risk or
      cause that is not present in the data you were given. If it is not in the data, it does
      not exist.
    - Do not predict the future and do not give clinical or medical treatment advice.
    - Every claim you make must trace to an item in the data.
    - Plain language. No jargon. Short sentences. A non-technical reader with no training
      should understand every line.
    - Do not invent facility names, people, dates, or context.
    PROMPT;

    public function __construct(private readonly AiChatClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Generate one AI product for a report payload.
     *
     * @param  array<string, mixed>  $payload  a frozen report snapshot payload
     * @return array{body: string, model: string, product: string, source_hash: string}
     *
     * @throws RuntimeException when unavailable, the product is unknown, or the API fails.
     */
    public function generate(array $payload, string $product): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('AI narrative is not configured.');
        }

        $config = AiProductCatalog::get($product);
        if ($config === null) {
            throw new RuntimeException("Unknown AI product: {$product}.");
        }

        $structured = $this->structuredInput($payload, $config);
        $system = self::BASE_RULES."\n\n".$config['instruction'];

        $body = $this->client->message(system: $system, user: $structured, maxTokens: 1024);

        return [
            'body' => $body,
            'model' => $this->client->model(),
            'product' => $product,
            // Ties the product to the exact intelligence it was written from, so a product
            // written from an older version of the report can be told apart from a current one.
            'source_hash' => hash('sha256', $structured),
        ];
    }

    /**
     * The only thing the model ever sees: the slice of the frozen intelligence this product is
     * entitled to, as compact readable text. Domain-scoped products (clinical, operational) see
     * only their domains' items — which is what makes the products genuinely different.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    private function structuredInput(array $payload, array $config): string
    {
        $intelligence = $payload['intelligence'] ?? [];
        $domains = $config['domains'] ?? [];
        $include = $config['include'] ?? [];
        $inScope = fn ($item) => $domains === []
            || in_array($item['measurement_domain'] ?? null, $domains, true)
            || ($item['category'] ?? $item['category_code'] ?? null) === 'CRITICAL_FINDING';

        $lines = ['REPORT: '.($payload['title'] ?? 'Health assessment')];

        if (in_array('score', $include, true)) {
            $score = $payload['score'] ?? [];
            $lines[] = 'OVERALL SCORE: '.($score['overall_score'] ?? 'not calibrated').' (calibration: '.($score['calibration_status'] ?? 'unknown').')';
        }

        if (in_array('findings', $include, true)) {
            $findings = collect($intelligence['findings'] ?? [])->filter($inScope);
            if ($findings->isNotEmpty()) {
                $lines[] = "\nFINDINGS:";
                foreach ($findings as $f) {
                    $line = '- ['.$f['category'].'/'.$f['severity'].'] '.$f['statement'];
                    if (! empty($f['consequence'])) {
                        $line .= ' Consequence if unaddressed: '.$f['consequence'];
                    }
                    $lines[] = $line;
                }
            }
        }

        if (in_array('root_causes', $include, true)) {
            $causes = $intelligence['root_causes'] ?? [];
            if ($causes !== []) {
                $lines[] = "\nROOT CAUSES (probable, inferred from the pattern):";
                foreach ($causes as $c) {
                    $lines[] = '- '.$c['statement'];
                }
            }
        }

        if (in_array('risks', $include, true)) {
            $risks = collect($intelligence['risks'] ?? [])->filter($inScope);
            if ($risks->isNotEmpty()) {
                $lines[] = "\nRISKS:";
                foreach ($risks as $r) {
                    $lines[] = '- ['.$r['level'].'] '.$r['statement'];
                }
            }
        }

        // Insight categories this product is entitled to lead with.
        $categories = $config['insight_categories'] ?? [];
        if ($categories !== []) {
            $insights = collect($intelligence['insights']['items'] ?? [])
                ->whereIn('category_code', $categories)->filter($inScope)
                ->unique(fn ($i) => $i['category_code'].'|'.$i['subject']);
            if ($insights->isNotEmpty()) {
                $lines[] = "\nKEY INSIGHTS:";
                foreach ($insights as $i) {
                    $lines[] = '- ['.$i['category_name'].'] '.$i['subject'];
                }
            }
        }

        if (in_array('recommendations', $include, true)) {
            $recs = collect($intelligence['recommendations'] ?? [])
                ->filter(fn ($r) => $domains === [] || in_array($r['measurement_domain'] ?? null, $domains, true));
            if ($recs->isNotEmpty()) {
                $lines[] = "\nRECOMMENDATIONS (each cites the finding it came from):";
                foreach ($recs as $rec) {
                    $lines[] = '- ('.$rec['horizon'].') '.$rec['statement'].' [from: '.($rec['from_finding']['statement'] ?? '').']';
                }
            }
        }

        $lines[] = "\nWrite using only the items above. If a section is empty, say so plainly.";

        return implode("\n", $lines);
    }
}
