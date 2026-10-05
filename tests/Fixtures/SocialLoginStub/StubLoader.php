<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\SocialLoginStub;

use Lightitlabs\Auth\Installers\SocialLoginInstaller;

/**
 * Renders the social login stubs into this fixture namespace so the Google token check and
 * the user linking run for real against firebase/php-jwt and an in-memory SQLite database.
 * The boilerplate's User, HttpException and UnauthenticatedException, and the shared
 * LoginByUserAction, are swapped for the fakes next to this loader.
 */
final class StubLoader
{
    private const FIXTURE_NAMESPACE = 'Lightitlabs\Tests\Fixtures\SocialLoginStub';

    public static function load(string ...$relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $tempFile = sys_get_temp_dir() . '/social-login-stub-' . md5($relativePath) . '.php';
            file_put_contents($tempFile, self::render($relativePath));

            require_once $tempFile;
        }
    }

    public static function migrateSocialAccountsTable(): void
    {
        $migration = require SocialLoginInstaller::stubDirectory()
            . '/database/migrations/create_social_accounts_table.stub';
        $migration->up();
    }

    private static function render(string $relativePath): string
    {
        $contents = (string) file_get_contents(SocialLoginInstaller::stubDirectory() . '/Auth/' . $relativePath);

        $contents = (string) preg_replace(
            '/^namespace Lightit\\\\[^;]+;/m',
            'namespace ' . self::FIXTURE_NAMESPACE . ';',
            $contents
        );
        $contents = (string) preg_replace('/^use Lightit\\\\Authentication\\\\[^;]+;\n/m', '', $contents);

        // Property-level #[\Override] needs PHP 8.5, the consumer app's version; this package's CI runs 8.4.
        if (\PHP_VERSION_ID < 80500) {
            $contents = (string) preg_replace(
                '/^[ \t]*#\[\\\\Override\]\n(?=[ \t]*(?:public|protected|private)[^(\n]*\$)/m',
                '',
                $contents
            );
        }

        return str_replace(
            [
                'use Lightit\Users\Domain\Models\User;',
                'use Lightit\Shared\App\Exceptions\Http\HttpException;',
                'use Lightit\Shared\App\Exceptions\Http\UnauthenticatedException;',
            ],
            [
                'use ' . self::FIXTURE_NAMESPACE . '\User;',
                'use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeHttpException as HttpException;',
                'use ' . self::FIXTURE_NAMESPACE . '\UnauthenticatedException;',
            ],
            $contents,
        );
    }
}
