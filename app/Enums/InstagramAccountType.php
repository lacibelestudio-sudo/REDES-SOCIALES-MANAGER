<?php

declare(strict_types=1);

namespace App\Enums;

enum InstagramAccountType: string
{
    case Business = 'business';
    case Creator = 'creator';
    case Unknown = 'unknown';

    public static function fromProvider(mixed $value): self
    {
        if (! is_string($value)) {
            return self::Unknown;
        }

        return match (strtoupper(trim($value))) {
            'BUSINESS' => self::Business,
            'CREATOR', 'MEDIA_CREATOR' => self::Creator,
            default => self::Unknown,
        };
    }
}
