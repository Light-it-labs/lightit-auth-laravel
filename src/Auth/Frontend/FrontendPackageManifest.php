<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Frontend;

final class FrontendPackageManifest
{
    private const LOCK_FILES = [
        'pnpm-lock.yaml' => 'pnpm',
        'yarn.lock' => 'yarn',
        'bun.lockb' => 'bun',
        'bun.lock' => 'bun',
        'package-lock.json' => 'npm',
    ];

    private const ADD_COMMANDS = [
        'pnpm' => 'pnpm add',
        'yarn' => 'yarn add',
        'bun' => 'bun add',
        'npm' => 'npm install',
    ];

    /**
     * @return array<mixed>|null
     */
    public function read(string $root): ?array
    {
        $path = $root.'/package.json';

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, string>
     */
    public function dependencies(string $root): array
    {
        $manifest = $this->read($root);

        if ($manifest === null) {
            return [];
        }

        $dependencies = [];

        foreach (['dependencies', 'devDependencies'] as $section) {
            $entries = $manifest[$section] ?? null;

            if (! \is_array($entries)) {
                continue;
            }

            foreach ($entries as $name => $constraint) {
                if (\is_string($name) && \is_string($constraint)) {
                    $dependencies[$name] = $constraint;
                }
            }
        }

        return $dependencies;
    }

    public function hasReactDependency(string $root): bool
    {
        $manifest = $this->read($root);

        if ($manifest === null) {
            return false;
        }

        $runtime = $manifest['dependencies'] ?? null;

        return \is_array($runtime) && \array_key_exists('react', $runtime);
    }

    public function packageManager(string $root): string
    {
        $manifest = $this->read($root);
        $declared = $manifest['packageManager'] ?? null;

        if (\is_string($declared) && preg_match('/^(pnpm|yarn|bun|npm)@/', $declared, $matches) === 1) {
            return $matches[1];
        }

        foreach (self::LOCK_FILES as $lockFile => $manager) {
            if (is_file($root.'/'.$lockFile)) {
                return $manager;
            }
        }

        return 'npm';
    }

    public function addCommand(string $root): string
    {
        return self::ADD_COMMANDS[$this->packageManager($root)];
    }

    public function majorVersion(string $constraint): ?int
    {
        $version = $this->normalizeVersion($constraint);

        if ($version === null) {
            return null;
        }

        return (int) explode('.', $version)[0];
    }

    public function satisfiesFloor(string $constraint, string $floor): bool
    {
        $version = $this->normalizeVersion($constraint);

        if ($version === null) {
            return true;
        }

        return version_compare($version, $floor, '>=');
    }

    public function normalizeVersion(string $constraint): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)*)/', $constraint, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
