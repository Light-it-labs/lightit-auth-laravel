<?php

declare(strict_types=1);

namespace Lightitlabs\Enums;

enum AuthDriver: string
{
    case Jwt = 'JWT';
    case Sanctum = 'Sanctum';
    case GoogleSso = 'Google SSO';
}
