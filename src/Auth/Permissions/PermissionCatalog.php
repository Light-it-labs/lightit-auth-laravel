<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Permissions;

final class PermissionCatalog
{
    /**
     * @return array<string, array{constant: string, holder: string, group: string}>
     */
    public static function entries(): array
    {
        return [
            'users.list' => ['constant' => 'LIST', 'holder' => 'UserPermissions', 'group' => 'users'],
            'users.get' => ['constant' => 'GET', 'holder' => 'UserPermissions', 'group' => 'users'],
            'users.create' => ['constant' => 'CREATE', 'holder' => 'UserPermissions', 'group' => 'users'],
            'users.delete' => ['constant' => 'DELETE', 'holder' => 'UserPermissions', 'group' => 'users'],
            'roles.read' => ['constant' => 'READ_ROLE', 'holder' => 'RolePermissions', 'group' => 'roles'],
            'roles.assign' => ['constant' => 'ASSIGN_ROLE', 'holder' => 'RolePermissions', 'group' => 'roles'],
            'permissions.read' => ['constant' => 'READ_PERMISSION', 'holder' => 'RolePermissions', 'group' => 'permissions'],
        ];
    }

    /**
     * Renders `public const X = 'name';` lines for the given holder class
     * (e.g. 'UserPermissions' or 'RolePermissions'). Lines after the first are
     * joined with the 4-space class-body indentation baked in, so the result
     * substitutes cleanly into a token sitting on its own already-indented line.
     */
    public function toPhpConstants(string $holder): string
    {
        $lines = [];

        foreach (self::entries() as $name => $entry) {
            if ($entry['holder'] !== $holder) {
                continue;
            }

            $lines[] = "public const {$entry['constant']} = '{$name}';";
        }

        return implode("\n\n    ", $lines);
    }

    /**
     * Renders the body of `PermissionManagement::PERMISSIONS` — one
     * `Holder::CONST => ['name' => Holder::CONST, 'group' => '...'],` line per
     * entry, holder-qualified. Lines after the first carry the 8-space
     * array-body indentation baked in, matching the token's placement.
     */
    public function toPhpRegistry(): string
    {
        $lines = [];

        foreach (self::entries() as $entry) {
            $reference = "{$entry['holder']}::{$entry['constant']}";

            $lines[] = "{$reference} => ['name' => {$reference}, 'group' => '{$entry['group']}'],";
        }

        return implode("\n        ", $lines);
    }

    /**
     * Renders a nested TypeScript object body, grouped by `group`. Leaf keys
     * are the camelCase form of each entry's constant name (LIST -> list,
     * READ_ROLE -> readRole); leaf values are exactly the permission names.
     */
    public function toTypeScriptConstants(): string
    {
        /** @var array<string, list<array{key: string, name: string}>> $groups */
        $groups = [];

        foreach (self::entries() as $name => $entry) {
            $groups[$entry['group']][] = [
                'key' => $this->camelCase($entry['constant']),
                'name' => $name,
            ];
        }

        $lines = [];

        foreach ($groups as $group => $groupEntries) {
            $lines[] = "{$group}: {";

            foreach ($groupEntries as $groupEntry) {
                $lines[] = "    {$groupEntry['key']}: '{$groupEntry['name']}',";
            }

            $lines[] = '},';
        }

        return implode("\n", $lines);
    }

    private function camelCase(string $constant): string
    {
        $words = explode('_', strtolower($constant));
        $first = (string) array_shift($words);

        foreach ($words as $word) {
            $first .= ucfirst($word);
        }

        return $first;
    }
}
