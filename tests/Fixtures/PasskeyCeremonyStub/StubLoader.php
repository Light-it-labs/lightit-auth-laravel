<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub;

use Lightitlabs\Auth\Installers\PasskeysInstaller;

/**
 * Renders passkey stubs into this fixture namespace so the ceremony runs for real
 * against web-auth/webauthn-lib and an in-memory SQLite `passkeys` table. The
 * boilerplate's User and HttpException are aliased to the fakes next to this loader.
 */
final class StubLoader
{
    private const FIXTURE_NAMESPACE = 'Lightitlabs\Tests\Fixtures\PasskeyCeremonyStub';

    public static function load(string ...$relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $tempFile = sys_get_temp_dir() . '/passkey-ceremony-stub-' . md5($relativePath) . '.php';
            file_put_contents($tempFile, self::render($relativePath));

            require_once $tempFile;
        }
    }

    public static function migratePasskeysTable(): void
    {
        $migration = require PasskeysInstaller::stubDirectory() . '/database/migrations/create_passkeys_table.stub';
        $migration->up();
    }

    private static function render(string $relativePath): string
    {
        $contents = (string) file_get_contents(PasskeysInstaller::stubDirectory() . '/Auth/' . $relativePath);

        $contents = (string) preg_replace(
            '/^namespace Lightit\\\\[^;]+;/m',
            'namespace ' . self::FIXTURE_NAMESPACE . ';',
            $contents
        );
        $contents = (string) preg_replace('/^use Lightit\\\\Authentication\\\\[^;]+;\n/m', '', $contents);

        return str_replace(
            [
                'use Lightit\Users\Domain\Models\User;',
                'use Lightit\Shared\App\Exceptions\Http\HttpException;',
            ],
            [
                'use ' . self::FIXTURE_NAMESPACE . '\User;',
                'use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeHttpException as HttpException;',
            ],
            $contents,
        );
    }
}
