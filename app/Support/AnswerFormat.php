<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Translates the answer formats an author chooses into the governed response types the
 * platform actually supports.
 *
 * Only formats backed by a publishable response type appear here. Date pickers and file
 * uploads are deliberately absent: `ResponseInputContract` does not support them, the
 * runtime cannot store or score them, and offering them would present controls that do
 * nothing. Adding one requires the full response-type contract in DEC-2026-07-18-006.
 */
final class AnswerFormat
{
    public const YES_NO = 'yes_no';

    public const YES_NO_NA = 'yes_no_na';

    public const MULTIPLE_CHOICE = 'multiple_choice';

    public const RATING = 'rating';

    public const NUMBER = 'number';

    public const TEXT = 'text';

    /**
     * @return array<string, array{key: string, label: string, description: string, type_code: string, choices: list<string>, author_defines_choices: bool}>
     */
    public static function all(): array
    {
        return [
            self::YES_NO => [
                'key' => self::YES_NO,
                'label' => 'Yes / No',
                'description' => 'A simple two-way answer.',
                'type_code' => 'SINGLE_SELECT',
                'choices' => ['Yes', 'No'],
                'author_defines_choices' => false,
            ],
            self::YES_NO_NA => [
                'key' => self::YES_NO_NA,
                'label' => 'Yes / No / Not applicable',
                'description' => 'For questions that will not apply everywhere.',
                'type_code' => 'SINGLE_SELECT',
                'choices' => ['Yes', 'No', 'Not applicable'],
                'author_defines_choices' => false,
            ],
            self::MULTIPLE_CHOICE => [
                'key' => self::MULTIPLE_CHOICE,
                'label' => 'Multiple choice',
                'description' => 'One answer chosen from a list you write.',
                'type_code' => 'SINGLE_SELECT',
                'choices' => [],
                'author_defines_choices' => true,
            ],
            self::RATING => [
                'key' => self::RATING,
                'label' => 'Rating (1 to 5)',
                'description' => 'A five point scale.',
                'type_code' => 'LIKERT',
                'choices' => ['1', '2', '3', '4', '5'],
                'author_defines_choices' => false,
            ],
            self::NUMBER => [
                'key' => self::NUMBER,
                'label' => 'Number',
                'description' => 'A measured value, such as a count.',
                'type_code' => 'NUMERIC',
                'choices' => [],
                'author_defines_choices' => false,
            ],
            self::TEXT => [
                'key' => self::TEXT,
                'label' => 'Written answer',
                'description' => 'A short note or explanation. Written answers are never scored.',
                'type_code' => 'OPEN_ENDED',
                'choices' => [],
                'author_defines_choices' => false,
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{key: string, label: string, description: string, type_code: string, choices: list<string>, author_defines_choices: bool}
     */
    public static function require(string $key): array
    {
        $format = self::all()[$key] ?? null;

        if (! $format) {
            throw ValidationException::withMessages([
                'format' => 'Choose how people should answer this question.',
            ]);
        }

        return $format;
    }

    /**
     * Resolves the answer choices stored against a question. Fixed formats always use their
     * own choices so an author cannot bend "Yes / No" into something else.
     *
     * @param  list<string>  $authored
     * @return list<string>
     */
    public static function choicesFor(array $format, array $authored): array
    {
        if (! $format['author_defines_choices']) {
            return $format['choices'];
        }

        $choices = collect($authored)
            ->map(fn ($choice) => trim((string) $choice))
            ->filter()
            ->values();

        if ($choices->count() < 2) {
            throw ValidationException::withMessages([
                'choices' => 'Add at least two answer choices.',
            ]);
        }

        if ($choices->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'choices' => 'Each answer choice must be different.',
            ]);
        }

        return $choices->all();
    }

    public static function labelForTypeCode(?string $typeCode, array $options = []): string
    {
        $labels = collect($options)->pluck('option_label')->filter()->values();

        return match ($typeCode) {
            'SINGLE_SELECT' => match (true) {
                $labels->count() === 2 && $labels->contains('Yes') && $labels->contains('No') => 'Yes / No',
                $labels->contains('Not applicable') => 'Yes / No / Not applicable',
                default => 'Multiple choice',
            },
            'LIKERT' => 'Rating',
            'NUMERIC' => 'Number',
            'OPEN_ENDED' => 'Written answer',
            default => $typeCode ?? 'Unknown',
        };
    }
}
