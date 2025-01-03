<?php

declare(strict_types=1);

namespace Lightit\Enums;

enum AuthDriver: string
{
    case JWT = 'JWT';
    case SANCTUM = 'Sanctum';
    case GOOGLE_SSO = 'Google SSO';
}
