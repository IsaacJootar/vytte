<?php

namespace App\Support;

final class LocalQuestionFormat
{
    public const YES_NO = 'YES_NO';

    public const YES_NO_NA = 'YES_NO_NA';

    public const SINGLE_SELECT = 'SINGLE_SELECT';

    public const MULTI_SELECT = 'MULTI_SELECT';

    public const SCALE_5 = 'SCALE_5';

    public const NUMERIC = 'NUMERIC';

    public const OPEN_ENDED = 'OPEN_ENDED';

    /** @return array<string, array{label: string, description: string, can_score: bool}> */
    public static function all(): array
    {
        return [
            self::YES_NO => ['label' => 'Yes or no', 'description' => 'A clear two-choice answer.', 'can_score' => true],
            self::YES_NO_NA => ['label' => 'Yes, no or not applicable', 'description' => 'Use when the question may not apply everywhere.', 'can_score' => true],
            self::SINGLE_SELECT => ['label' => 'Choose one option', 'description' => 'One answer from a list you provide.', 'can_score' => false],
            self::MULTI_SELECT => ['label' => 'Choose all that apply', 'description' => 'Several answers may be selected.', 'can_score' => false],
            self::SCALE_5 => ['label' => 'Rating scale (1 to 5)', 'description' => 'A five-point rating with clear low and high meanings.', 'can_score' => true],
            self::NUMERIC => ['label' => 'Number', 'description' => 'A count, percentage, duration or other measured value.', 'can_score' => false],
            self::OPEN_ENDED => ['label' => 'Written answer', 'description' => 'A note, explanation or observation.', 'can_score' => false],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function canScore(string $type): bool
    {
        return self::all()[$type]['can_score'] ?? false;
    }

    public static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    public static function normalizeAnswer(array $question, mixed $value): mixed
    {
        if (self::isBlank($value)) {
            return null;
        }

        $type = $question['response_type'] ?? self::YES_NO;
        $choices = collect($question['choices'] ?? [])->map(fn ($choice) => (string) $choice)->all();

        return match ($type) {
            self::YES_NO => in_array(strtoupper((string) $value), ['YES', 'NO'], true) ? strtoupper((string) $value) : null,
            self::YES_NO_NA => in_array(strtoupper((string) $value), ['YES', 'NO', 'NOT_APPLICABLE'], true) ? strtoupper((string) $value) : null,
            self::SINGLE_SELECT => in_array((string) $value, $choices, true) ? (string) $value : null,
            self::MULTI_SELECT => is_array($value)
                ? collect($value)->map(fn ($answer) => (string) $answer)->filter(fn ($answer) => in_array($answer, $choices, true))->unique()->values()->all()
                : null,
            self::SCALE_5 => in_array((int) $value, [1, 2, 3, 4, 5], true) ? (string) ((int) $value) : null,
            self::NUMERIC => is_numeric($value) && self::withinNumericRange($question, (float) $value) ? (string) $value : null,
            self::OPEN_ENDED => mb_strlen(trim((string) $value)) <= 5000 ? trim((string) $value) : null,
            default => null,
        };
    }

    public static function displayAnswer(array $question, mixed $answer): ?string
    {
        if (self::isBlank($answer)) {
            return null;
        }

        if (($question['response_type'] ?? null) === self::MULTI_SELECT) {
            return collect($answer)->join(', ');
        }

        if (($question['response_type'] ?? null) === self::YES_NO_NA && $answer === 'NOT_APPLICABLE') {
            return 'Not applicable';
        }

        if (($question['response_type'] ?? null) === self::NUMERIC) {
            return trim((string) $answer.' '.($question['numeric_unit'] ?? ''));
        }

        return match ($answer) {
            'YES' => 'Yes',
            'NO' => 'No',
            default => (string) $answer,
        };
    }

    private static function withinNumericRange(array $question, float $value): bool
    {
        $minimum = $question['numeric_min'] ?? null;
        $maximum = $question['numeric_max'] ?? null;

        return ($minimum === null || $minimum === '' || $value >= (float) $minimum)
            && ($maximum === null || $maximum === '' || $value <= (float) $maximum);
    }
}
