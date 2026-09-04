<?php

declare(strict_types=1);

use Lightitlabs\Auth\Permissions\PermissionCatalog;
use Lightitlabs\Tools\StubRenderer;

function permissionStubsPath(string $relativePath): string
{
    return __DIR__.'/../../../../src/Stubs/LaravelPermissions/'.$relativePath;
}

describe('PermissionCatalog::toPhpConstants()', function (): void {
    it('renders one public const line per UserPermissions entry', function (): void {
        $catalog = new PermissionCatalog;

        expect($catalog->toPhpConstants('UserPermissions'))->toBe(
            "public const LIST = 'users.list';\n\n".
            "    public const GET = 'users.get';\n\n".
            "    public const CREATE = 'users.create';\n\n".
            "    public const DELETE = 'users.delete';"
        );
    });

    it('renders one public const line per RolePermissions entry', function (): void {
        $catalog = new PermissionCatalog;

        expect($catalog->toPhpConstants('RolePermissions'))->toBe(
            "public const READ_ROLE = 'roles.read';\n\n".
            "    public const ASSIGN_ROLE = 'roles.assign';\n\n".
            "    public const READ_PERMISSION = 'permissions.read';"
        );
    });

    it('renders nothing for a holder with no entries', function (): void {
        $catalog = new PermissionCatalog;

        expect($catalog->toPhpConstants('NoSuchHolder'))->toBe('');
    });
});

describe('PermissionCatalog::toPhpRegistry()', function (): void {
    it('renders one holder-qualified registry line per catalog entry', function (): void {
        $catalog = new PermissionCatalog;

        expect($catalog->toPhpRegistry())->toBe(
            "UserPermissions::LIST => ['name' => UserPermissions::LIST, 'group' => 'users'],\n        ".
            "UserPermissions::GET => ['name' => UserPermissions::GET, 'group' => 'users'],\n        ".
            "UserPermissions::CREATE => ['name' => UserPermissions::CREATE, 'group' => 'users'],\n        ".
            "UserPermissions::DELETE => ['name' => UserPermissions::DELETE, 'group' => 'users'],\n        ".
            "RolePermissions::READ_ROLE => ['name' => RolePermissions::READ_ROLE, 'group' => 'roles'],\n        ".
            "RolePermissions::ASSIGN_ROLE => ['name' => RolePermissions::ASSIGN_ROLE, 'group' => 'roles'],\n        ".
            "RolePermissions::READ_PERMISSION => ['name' => RolePermissions::READ_PERMISSION, 'group' => 'permissions'],"
        );
    });
});

describe('PermissionCatalog::toTypeScriptConstants()', function (): void {
    it('groups leaf entries by group with camelCase keys and permission-name values', function (): void {
        $catalog = new PermissionCatalog;

        expect($catalog->toTypeScriptConstants())->toBe(
            "users: {\n".
            "    list: 'users.list',\n".
            "    get: 'users.get',\n".
            "    create: 'users.create',\n".
            "    delete: 'users.delete',\n".
            "},\n".
            "roles: {\n".
            "    readRole: 'roles.read',\n".
            "    assignRole: 'roles.assign',\n".
            "},\n".
            "permissions: {\n".
            "    readPermission: 'permissions.read',\n".
            '},'
        );
    });
});

describe('PermissionCatalog consistency with rendered PHP stubs', function (): void {
    it('renders exactly the catalog\'s permission names as constants, in both directions', function (): void {
        $catalog = new PermissionCatalog;
        $renderer = new StubRenderer;

        $tokens = [
            'userPermissionConstants' => $catalog->toPhpConstants('UserPermissions'),
            'rolePermissionConstants' => $catalog->toPhpConstants('RolePermissions'),
        ];

        $userRendered = $renderer->render(permissionStubsPath('Permissions/UserPermissions.stub'), $tokens);
        $roleRendered = $renderer->render(permissionStubsPath('Permissions/RolePermissions.stub'), $tokens);

        preg_match_all('/public const \w+ = \'([^\']+)\';/', $userRendered, $userMatches);
        preg_match_all('/public const \w+ = \'([^\']+)\';/', $roleRendered, $roleMatches);

        $renderedNames = array_merge($userMatches[1], $roleMatches[1]);
        sort($renderedNames);

        $catalogNames = array_keys(PermissionCatalog::entries());
        sort($catalogNames);

        // Both directions: nothing in the catalog is missing from the rendered
        // constants, and nothing rendered exists outside the catalog.
        expect($renderedNames)->toBe($catalogNames);
    });

    it('renders every catalog [name => group] pair in the permission registry', function (): void {
        $catalog = new PermissionCatalog;
        $renderer = new StubRenderer;
        $stubPath = permissionStubsPath('Permissions/PermissionManagement.stub');

        $rendered = $renderer->render($stubPath, ['permissionRegistry' => $catalog->toPhpRegistry()]);

        // Whole-file comparison, not a toContain fragment: the expected file is
        // built by substituting the token directly into the raw stub, so any
        // corruption anywhere in the rendered output — not just around the
        // registry entries themselves — fails this assertion.
        $expected = str_replace(
            '{{ permissionRegistry }}',
            $catalog->toPhpRegistry(),
            (string) file_get_contents($stubPath)
        );

        expect($rendered)->toBe($expected);
    });

    it('renders exactly the catalog\'s permission names as TypeScript constants, in both directions', function (): void {
        $catalog = new PermissionCatalog;
        $renderer = new StubRenderer;

        $rendered = $renderer->render(
            __DIR__.'/../../../../src/Stubs/Frontend/LaravelPermissions/services/permissions/constants.ts.stub',
            ['permissionConstants' => $catalog->toTypeScriptConstants()]
        );

        // Tolerant of whitespace and either quote style: this only needs to
        // extract permission-name leaf values, not certify formatting, so it
        // must not be brittle to a non-semantic change in the TS renderer's
        // quote style or spacing.
        preg_match_all('/:\s*[\'"]([^\'"]+)[\'"],/', $rendered, $matches);

        $renderedNames = $matches[1];
        sort($renderedNames);

        $catalogNames = array_keys(PermissionCatalog::entries());
        sort($catalogNames);

        // Both directions: nothing in the catalog is missing from the rendered
        // TypeScript constants, and nothing rendered exists outside the catalog.
        expect($renderedNames)->toBe($catalogNames);
    });
});
