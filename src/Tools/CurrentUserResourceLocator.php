<?php

declare(strict_types=1);

namespace Lightitlabs\Tools;

/**
 * Finds the consumer-authored resource class that represents the current user in
 * an API response, so `PhpResourcePatcher` has something to patch. Path-injected
 * and Command-free, mirroring `FrontendProjectLocator`'s shape.
 */
final class CurrentUserResourceLocator
{
    private const CANDIDATES = [
        'app/Http/Resources/UserResource.php',
        'app/Http/Resources/CurrentUserResource.php',
        'src/Users/App/Resources/UserResource.php',
    ];

    public function locate(string $applicationRoot, ?string $explicitPath = null): ?string
    {
        foreach ($this->candidates($applicationRoot, $explicitPath) as $candidate) {
            if ($this->looksLikeAJsonResource($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function rejectionReason(string $path): string
    {
        if (! file_exists($path)) {
            return "file does not exist: {$path}";
        }

        return "exists but does not look like a JsonResource with toArray: {$path}";
    }

    /**
     * @return list<string>
     */
    private function candidates(string $applicationRoot, ?string $explicitPath): array
    {
        if ($explicitPath !== null && $explicitPath !== '') {
            return [$explicitPath];
        }

        $root = rtrim($applicationRoot, '/\\');

        return array_map(
            static fn (string $relative): string => $root.\DIRECTORY_SEPARATOR.$relative,
            self::CANDIDATES
        );
    }

    private function looksLikeAJsonResource(string $path): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        return str_contains($contents, 'function toArray') && str_contains($contents, 'JsonResource');
    }
}
