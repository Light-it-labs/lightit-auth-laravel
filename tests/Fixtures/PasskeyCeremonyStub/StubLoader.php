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

    /**
     * @var array<string, true>
     */
    private static array $loaded = [];

    public static function load(string ...$relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            if (isset(self::$loaded[$relativePath])) {
                continue;
            }

            $tempFile = sys_get_temp_dir() . '/passkey-ceremony-stub-' . bin2hex(random_bytes(8)) . '.php';
            file_put_contents($tempFile, self::render($relativePath));

            try {
                require $tempFile;
            } finally {
                unlink($tempFile);
            }

            self::$loaded[$relativePath] = true;
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
            ],
            [
                'use ' . self::FIXTURE_NAMESPACE . '\User;',
                'use Lightitlabs\Tests\Fixtures\TwoFactorAccountStub\FakeHttpException as HttpException;',
            ],
            $contents,
        );
    }
}
