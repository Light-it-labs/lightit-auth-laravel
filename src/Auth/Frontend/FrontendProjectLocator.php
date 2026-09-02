<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Frontend;

use RuntimeException;

final class FrontendProjectLocator
{
    private const SIBLING_CANDIDATES = ['frontend', 'front'];

    public function __construct(private readonly FrontendPackageManifest $manifest) {}

    public function locate(string $laravelRoot, ?string $explicitPath = null): ?string
    {
        foreach ($this->candidates($laravelRoot, $explicitPath) as $candidate) {
            $resolved = realpath($candidate);

            if ($resolved === false || ! is_dir($resolved)) {
                continue;
            }

            if (! $this->manifest->hasReactDependency($resolved)) {
                continue;
            }

            return $resolved;
        }

        return null;
    }

    public function rejectionReason(string $path): string
    {
        $resolved = realpath($path);

        if ($resolved === false || ! is_dir($resolved)) {
            return "not a directory: {$path}";
        }

        if ($this->manifest->read($resolved) === null) {
            return "no readable package.json in {$resolved}";
        }

        return "package.json in {$resolved} does not list react as a dependency";
    }

    public function resolveDestination(string $root, string $relativePath): string
    {
        $realRoot = realpath($root);

        if ($realRoot === false) {
            throw new RuntimeException("Frontend root does not exist: {$root}");
        }

        $destination = $realRoot.\DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');

        $this->assertContained($realRoot, $this->deepestExistingAncestor(\dirname($destination)));

        if (file_exists($destination)) {
            $this->assertContained($realRoot, realpath($destination));
        }

        return $destination;
    }

    /**
     * @return list<string>
     */
    private function candidates(string $laravelRoot, ?string $explicitPath): array
    {
        if ($explicitPath !== null && $explicitPath !== '') {
            return [$explicitPath];
        }

        $parent = \dirname($laravelRoot);
        $candidates = [];

        foreach (self::SIBLING_CANDIDATES as $sibling) {
            $candidates[] = $parent.\DIRECTORY_SEPARATOR.$sibling;
        }

        $candidates[] = $parent.\DIRECTORY_SEPARATOR.basename($laravelRoot).'-frontend';

        return $candidates;
    }

    private function deepestExistingAncestor(string $directory): string|false
    {
        while (! is_dir($directory) && $directory !== \dirname($directory)) {
            $directory = \dirname($directory);
        }

        return realpath($directory);
    }

    private function assertContained(string $root, string|false $path): void
    {
        $contained = $path !== false
            && ($path === $root || str_starts_with($path, rtrim($root, '/\\').\DIRECTORY_SEPARATOR));

        if (! $contained) {
            throw new RuntimeException("Refusing to write outside the frontend root: {$root}");
        }
    }
}
