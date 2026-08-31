<?php

declare(strict_types=1);

namespace Lightitlabs\Enums;

enum AuthDriver: string
{
    case SanctumApiToken = 'sanctum-token';
    case SanctumCookie = 'sanctum-cookie';
    case GoogleSso = 'google-sso';

    public function label(): string
    {
        return match ($this) {
            self::SanctumApiToken => 'Sanctum API Token',
            self::SanctumCookie => 'Sanctum Cookie (SPA)',
            self::GoogleSso => 'Google SSO',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
