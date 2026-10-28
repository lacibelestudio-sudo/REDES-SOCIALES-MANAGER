<?php

declare(strict_types=1);

namespace App\Enums;

enum LinkedInOrganizationDiscoveryStatus: string
{
    case Succeeded = 'succeeded';
    case PermissionDenied = 'permission_denied';
    case AuthenticationFailed = 'authentication_failed';
    case RateLimited = 'rate_limited';
    case ConnectionFailed = 'connection_failed';
    case ProviderFailed = 'provider_failed';

    public function userMessage(): ?string
    {
        return match ($this) {
            self::Succeeded => null,
            self::PermissionDenied => 'LinkedIn denied access to organization Pages. You can still connect your personal profile; approved Community Management permissions are needed to discover Pages.',
            self::AuthenticationFailed => 'LinkedIn rejected the organization lookup. You can still connect your personal profile; reconnect if you need to discover Pages.',
            self::RateLimited => 'LinkedIn rate-limited the organization lookup. You can still connect your personal profile; try discovering Pages again later.',
            self::ConnectionFailed => 'The organization lookup could not reach LinkedIn. You can still connect your personal profile; try discovering Pages again later.',
            self::ProviderFailed => 'LinkedIn could not complete the organization lookup. You can still connect your personal profile; try again later.',
        };
    }
}
