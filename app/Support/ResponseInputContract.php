<?php

namespace App\Support;

final class ResponseInputContract
{
    public const OPTION_TYPES = ['SINGLE_SELECT', 'LIKERT'];

    public const TEXT_TYPES = ['OPEN_ENDED'];

    public const NUMERIC_TYPES = ['NUMERIC'];

    public const SUPPORTED_TYPES = [
        ...self::OPTION_TYPES,
        ...self::TEXT_TYPES,
        ...self::NUMERIC_TYPES,
    ];

    public static function supports(?string $type): bool
    {
        return in_array($type, self::SUPPORTED_TYPES, true);
    }
}
