<?php

namespace App\Support;

use App\Models\Assessment;

/**
 * Which report a template produces.
 *
 * A template declares its report type once, inside the `report` key of its catalogue release's
 * aggregation policy. That policy is frozen into the release's content hash and copied into
 * every assessment snapshot, so the type a template was published with is the type every report
 * from it is drawn with — it never changes underneath a finished assessment.
 *
 * STANDARD is the general diagnostic report (scores, lenses, findings). PROGRAMME is the
 * evaluation report used by programme templates: a site profile, a classification, and a
 * programme overview, with no lenses.
 */
final class ReportType
{
    public const STANDARD = 'STANDARD';

    public const PROGRAMME = 'PROGRAMME';

    public const ALL = [self::STANDARD, self::PROGRAMME];

    /** A programme report built from many respondents' answers, not one focal person's. */
    public const KIND_SURVEY = 'SURVEY';

    public static function of(Assessment $assessment): string
    {
        $type = strtoupper((string) data_get($assessment->snapshot?->aggregation_policy, 'report.type', self::STANDARD));

        return in_array($type, self::ALL, true) ? $type : self::STANDARD;
    }

    public static function isProgramme(Assessment $assessment): bool
    {
        return self::of($assessment) === self::PROGRAMME;
    }

    public static function isSurvey(Assessment $assessment): bool
    {
        return self::isProgramme($assessment)
            && strtoupper((string) data_get(self::config($assessment), 'kind', '')) === self::KIND_SURVEY;
    }

    /** @return array<string, mixed> */
    public static function config(Assessment $assessment): array
    {
        return (array) data_get($assessment->snapshot?->aggregation_policy, 'report', []);
    }
}
