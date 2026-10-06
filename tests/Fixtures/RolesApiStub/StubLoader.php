<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Fixtures\RolesApiStub;

/**
 * Renders the roles stubs into this fixture namespace so they run for real against
 * spatie/laravel-permission on SQLite: every `Lightit\...` class the stubs reference
 * resolves to a stub loaded here or to the `User` and `HttpException` fixtures next to
 * this loader, which stand in for the boilerplate's own classes.
 */
final class StubLoader
{
    public const FIXTURE_NAMESPACE = 'Lightitlabs\Tests\Fixtures\RolesApiStub';

    public static function load(string ...$relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            require_once self::renderToFile($relativePath);
        }
    }

    public static function renderToFile(string $relativePath): string
    {
        $path = sys_get_temp_dir() . '/roles-api-stub-' . md5($relativePath) . '.php';
        file_put_contents($path, self::render($relativePath));

        return $path;
    }

    public static function render(string $relativePath): string
    {
        $contents = (string) file_get_contents(__DIR__ . '/../../../src/Stubs/LaravelPermissions/' . $relativePath);

        $contents = (string) preg_replace(
            '/^namespace [^;]+;/m',
            'namespace ' . self::FIXTURE_NAMESPACE . ';',
            $contents
        );

        return (string) preg_replace(
            '/^use Lightit\\\\(?:[A-Za-z]+\\\\)*([A-Za-z]+);/m',
            'use ' . self::FIXTURE_NAMESPACE . '\\\\$1;',
            $contents
        );
    }

    /**
     * A fully qualified line from a checklist, pointed at the fixtures instead of the boilerplate.
     */
    public static function fixtureLine(string $line): string
    {
        return (string) preg_replace(
            '/\\\\Lightit\\\\(?:[A-Za-z]+\\\\)*([A-Za-z]+)/',
            '\\\\' . str_replace('\\', '\\\\', self::FIXTURE_NAMESPACE) . '\\\\$1',
            $line,
        );
    }
}
