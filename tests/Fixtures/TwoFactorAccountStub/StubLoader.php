<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\TwoFactorAccountStub;

/**
 * Renders 2FA stubs into this fixture namespace so their actions run for real
 * against in-memory fakes: every `Lightit\Authentication\...` class lands in
 * the same namespace, and the consumer-only classes (User, HttpException,
 * Google2FA) are aliased to the fakes next to this loader.
 */
final class StubLoader
{
    private const FIXTURE_NAMESPACE = 'Lightitlabs\Tests\Fixtures\TwoFactorAccountStub';

    public static function load(string ...$relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $tempFile = sys_get_temp_dir() . '/two-factor-account-stub-' . md5($relativePath) . '.php';
            file_put_contents($tempFile, self::render($relativePath));

            require_once $tempFile;
        }
    }

    private static function render(string $relativePath): string
    {
        $contents = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/' . $relativePath);

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
                'use PragmaRX\Google2FALaravel\Google2FA;',
            ],
            [
                'use ' . self::FIXTURE_NAMESPACE . '\FakeUser as User;',
                'use ' . self::FIXTURE_NAMESPACE . '\FakeHttpException as HttpException;',
                'use ' . self::FIXTURE_NAMESPACE . '\FakeGoogle2FA as Google2FA;',
            ],
            $contents,
        );
    }
}
