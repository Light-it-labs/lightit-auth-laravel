<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

/**
 * Splices a marker-guarded `require` for a generated route file into a parent route file
 * (e.g. `routes/api.php`), and scans that same parent for routes the splice would shadow.
 *
 * Path-injected and Command-free by design: every method takes an absolute path rather than
 * calling `base_path()`, so it can be pointed at a temp directory in a unit test.
 */
final class RouteFileRegistrar
{
    private const MARKER_PREFIX = '// lightit-auth: ';

    private const MARKER_SUFFIX = ' routes';

    public function marker(string $label): string
    {
        return self::MARKER_PREFIX.$label.self::MARKER_SUFFIX;
    }

    public function requireStatement(string $routeFileName): string
    {
        return "require __DIR__.'/{$routeFileName}';";
    }

    public function register(string $parentRouteFile, string $routeFileName, string $label): RouteRegistrationOutcome
    {
        if (! file_exists($parentRouteFile)) {
            return RouteRegistrationOutcome::ParentMissing;
        }

        $original = file_get_contents($parentRouteFile);

        if ($original === false) {
            return RouteRegistrationOutcome::Failed;
        }

        $marker = $this->marker($label);

        if (str_contains($original, $marker)) {
            return RouteRegistrationOutcome::AlreadyRegistered;
        }

        $appended = rtrim($original).PHP_EOL.PHP_EOL
            .$marker.PHP_EOL
            .$this->requireStatement($routeFileName).PHP_EOL;

        if (file_put_contents($parentRouteFile, $appended) === false) {
            return $this->restore($parentRouteFile, $original);
        }

        $written = file_get_contents($parentRouteFile);

        if ($written === false || ! str_contains($written, $marker)) {
            return $this->restore($parentRouteFile, $original);
        }

        return RouteRegistrationOutcome::Registered;
    }

    /**
     * @param  array<string, string>  $patterns  route label => regex
     * @return list<string> labels whose pattern matched
     */
    public function shadowedRoutes(string $parentRouteFile, array $patterns): array
    {
        if (! file_exists($parentRouteFile)) {
            return [];
        }

        $contents = (string) file_get_contents($parentRouteFile);
        $shadowed = [];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $shadowed[] = $label;
            }
        }

        return $shadowed;
    }

    private function restore(string $path, string $original): RouteRegistrationOutcome
    {
        return file_put_contents($path, $original) === false
            ? RouteRegistrationOutcome::Corrupted
            : RouteRegistrationOutcome::Failed;
    }
}
