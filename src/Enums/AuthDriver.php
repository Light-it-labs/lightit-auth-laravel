<?php

declare(strict_types=1);

namespace Lightit\Enums;

enum AuthDriver: string
{
    case Jwt = 'JWT';
    case Sanctum = 'Sanctum';
    case GoogleSso = 'Google SSO';
}
