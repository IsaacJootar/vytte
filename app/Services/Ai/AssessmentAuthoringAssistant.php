<?php

namespace App\Services\Ai;

use App\Models\DepartmentFrameworkVersion;
use App\Services\FrameworkContentService;
use RuntimeException;

class AssessmentAuthoringAssistant
{
    public function __construct(private readonly AiChatClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /** @return array{model: string, findings: list<array{severity: string, code: string, message: string}>} */
    public function lint(DepartmentFrameworkVersion $framework): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('AI authoring assistance is not configured.');
        }
        $payload = app(FrameworkContentService::class)->frameworkPayload($framework);
        $source = json_encode([
            'purpose' => $payload['purpose'] ?? null,
            'source' => [
                'authority' => $framework->source_authority,
                'url' => $framework->source_url,
                'licence' => $framework->license_code,
                'summary' => $payload['source_summary'] ?? null,
            ],
            'sections' => $payload['sections'] ?? [],
            'questions' => collect($payload['questions'] ?? [])->map(fn ($question) => [
                'question_id' => $question['question_id'],
                'text' => $question['question_text'],
                'response_type' => $question['response_type'],
                'options' => collect($question['options'] ?? [])->pluck('option_label')->all(),
                'help_text' => $question['help_text'] ?? null,
                'applicability' => $question['applicability'] ?? null,
            ])->all(),
        ], JSON_THROW_ON_ERROR);
        $text = $this->client->message(
            system: 'You are a health-assessment authoring reviewer. Identify ambiguity, double-barrelled wording, bias, missing definitions or timeframes, answer-set problems, respondent burden, and unsafe branching. Use only the supplied content. Do not invent sources, scores, facts, approvals, or replacement questions. Return concise reviewer notes with a severity label. Your output is advice only and can never approve or publish content.',
            user: $source,
            maxTokens: 1400,
        );

        return [
            'model' => $this->client->model(),
            'findings' => [['severity' => 'ADVISORY', 'code' => 'AI_REVIEW', 'message' => $text]],
        ];
    }
}
