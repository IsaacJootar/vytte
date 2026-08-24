<?php

namespace App\Support;

final class ResponseInputContract
{
    public const OPTION_TYPES = ['SINGLE_SELECT', 'MULTI_SELECT', 'LIKERT'];

    public const TEXT_TYPES = ['OPEN_ENDED'];

    public const NUMERIC_TYPES = ['NUMERIC'];

    /**
     * Respondent names their own items (not chosen from an author-defined list) and picks
     * one as the single top priority — "rank your top 3, then tell us which one you'd solve
     * first." Structurally unscored, same reason OPEN_ENDED is: there is no predetermined
     * good/bad direction for what someone names as their own priority.
     */
    public const RANKED_TYPES = ['RANKING'];

    /** Types with no inherent maturity/quality scale — never eligible to carry a score. */
    public const UNSCORABLE_TYPES = [
        ...self::TEXT_TYPES,
        ...self::RANKED_TYPES,
    ];

    public const SUPPORTED_TYPES = [
        ...self::OPTION_TYPES,
        ...self::TEXT_TYPES,
        ...self::NUMERIC_TYPES,
        ...self::RANKED_TYPES,
    ];

    public const RESPONSE_STATES = [
        'ANSWERED',
        'NOT_APPLICABLE',
        'UNKNOWN',
        'NOT_ASSESSED',
        'NOT_OBSERVED',
        'DECLINED',
        'MISSING',
    ];

    public static function supports(?string $type): bool
    {
        return in_array($type, self::SUPPORTED_TYPES, true);
    }
}
