<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

use Composer\InstalledVersions;
use OutOfBoundsException;

final class PackageVersion
{
    private const PACKAGE_NAME = 'light-it-labs/lightit-auth-laravel';

    public function __construct(
        private readonly string $packageName = self::PACKAGE_NAME,
    ) {}

    public function resolve(): string
    {
        try {
            return InstalledVersions::getPrettyVersion($this->packageName) ?? 'unknown';
        } catch (OutOfBoundsException) {
            return 'unknown';
        }
    }
}
