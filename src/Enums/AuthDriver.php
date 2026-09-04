<?php

declare(strict_types=1);

namespace Lightitlabs\Enums;

enum AuthDriver: string
{
    case SanctumApiToken = 'Sanctum API Token';
    case GoogleSso = 'Google SSO';
}
