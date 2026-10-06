<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeLaravelPermissionFrontendCommand;

/**
 * The react-template (develop) modules the generated files import; anything else under
 * `@/` must be a file this installer writes.
 */
const ROLES_TEMPLATE_MODULES = [
    '@/components/ui/badge',
    '@/components/ui/button',
    '@/components/ui/checkbox',
    '@/components/ui/data-table',
    '@/components/ui/dialog',
    '@/components/ui/error-message',
    '@/components/ui/label',
    '@/components/ui/table',
    '@/config/api',
    '@/config/query-client',
    '@/constants/pagination',
    '@/hooks/use-pagination',
    '@/i18n',
    '@/services/auth/actions',
    '@/services/schemas',
    '@/services/types',
    '@/services/users/types',
];

/**
 * i18n keys the generated files use that react-template's en.json already has.
 */
const ROLES_TEMPLATE_I18N_KEYS = ['buttons.cancel'];

describe('LaravelPermissionFrontendInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/lightit-roles-frontend-' . bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__ . '/../Fixtures/frontend/react-project', $this->root);

        $this->generatedFiles = [
            'src/services/permissions/constants.ts',
            'src/services/permissions/schemas.ts',
            'src/services/permissions/types.ts',
            'src/services/permissions/api.ts',
            'src/services/permissions/factories.ts',
            'src/services/permissions/actions.ts',
            'src/services/permissions/ensure-permission.ts',
            'src/services/roles/schemas.ts',
            'src/services/roles/types.ts',
            'src/services/roles/api.ts',
            'src/services/roles/factories.ts',
            'src/services/roles/actions.ts',
            'src/components/permissions/can.tsx',
            'src/routes/_private/roles/-hooks/use-user-roles-table.tsx',
            'src/routes/_private/roles/-components/edit-user-roles-dialog.tsx',
            'src/routes/_private/roles/page.tsx',
        ];

        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand($this->root));
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('writes the permission helpers, the roles page and the checklist with no placeholder left', function (): void {
        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        foreach ([...$this->generatedFiles, 'AUTH-ROLES-FRONTEND-TODO.md'] as $relative) {
            expect($this->root . '/' . $relative)->toBeFile()
                ->and(File::get($this->root . '/' . $relative))->not->toMatch('/\{\{\s*[a-zA-Z]+\s*\}\}/');
        }
    });

    it('skips every file on a second run and leaves an existing one intact', function (): void {
        File::ensureDirectoryExists($this->root . '/src/components/permissions');
        File::put($this->root . '/src/components/permissions/can.tsx', 'export const Can = () => null;');

        $this->artisan('laravel-permission-frontend-fake')
            ->expectsOutputToContain('Skipped src/components/permissions/can.tsx: the file already exists.')
            ->assertSuccessful();

        $pending = $this->artisan('laravel-permission-frontend-fake');

        foreach ([...$this->generatedFiles, 'AUTH-ROLES-FRONTEND-TODO.md'] as $relative) {
            $pending->expectsOutputToContain("Skipped {$relative}: the file already exists.");
        }

        $pending->doesntExpectOutputToContain('Created: ')->assertSuccessful();

        expect(File::get($this->root . '/src/components/permissions/can.tsx'))->toBe('export const Can = () => null;');
    });

    it('writes no comment into the generated TypeScript', function (): void {
        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        foreach ($this->generatedFiles as $relative) {
            expect(File::get($this->root . '/' . $relative))->not->toMatch('#^\s*(//|/\*)#m');
        }
    });

    it('only imports react-template modules or files it generates', function (): void {
        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        $generatedModules = array_map(
            static fn (string $path): string => '@/' . preg_replace('#^src/|\.tsx?$#', '', $path),
            $this->generatedFiles,
        );

        foreach ($this->generatedFiles as $relative) {
            preg_match_all('#from "(@/[^"]+)"#', File::get($this->root . '/' . $relative), $matches);

            foreach ($matches[1] as $module) {
                expect([...ROLES_TEMPLATE_MODULES, ...$generatedModules])->toContain($module);
            }
        }
    });

    it('lists in the checklist every i18n key the generated files use', function (): void {
        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        $todo = File::get($this->root . '/AUTH-ROLES-FRONTEND-TODO.md');
        preg_match_all('/```json\n(.*?)\n```/s', $todo, $blocks);

        $listed = [];

        foreach ($blocks[1] as $block) {
            $decoded = json_decode(str_starts_with(trim($block), '{') ? $block : '{' . $block . '}', true);
            expect($decoded)->toBeArray();
            $listed = [...$listed, ...array_keys(Arr::dot($decoded))];
        }

        $used = [];

        foreach ($this->generatedFiles as $relative) {
            preg_match_all('/\bt\("([^"]+)"/', File::get($this->root . '/' . $relative), $matches);
            $used = [...$used, ...$matches[1]];
        }

        expect($used)->not->toBeEmpty();

        foreach (array_unique($used) as $key) {
            expect([...$listed, ...ROLES_TEMPLATE_I18N_KEYS])->toContain($key);
        }

        expect($listed)->toContain('navigation.links.roles');
    });

    it('writes the checklist with the missing dependency, the sidebar snippet and folded i18n keys', function (): void {
        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        $todo = File::get($this->root . '/AUTH-ROLES-FRONTEND-TODO.md');

        expect($todo)
            ->toStartWith("# Roles and permissions setup checklist (frontend)\n\n> Generated by `auth:setup`.")
            ->toContain('https://github.com/Light-it-labs/lightit-auth-laravel/blob/main/docs/permission.md')
            ->toContain(
                "- [ ] **Install dependencies** (`pnpm`): Missing dependencies. Run:\n\n```sh\npnpm add @tanstack/react-table\n```"
            )
            ->toContain('import { Can } from "@/components/permissions/can";')
            ->toContain('<Can permission={PERMISSIONS.manageRoles}>')
            ->toContain('path="/roles"')
            ->toContain("<details>\n<summary>i18n keys to paste</summary>")
            ->toContain('pnpm exec tsc --noEmit')
            ->and(substr_count($todo, "\n- [ ] "))->toBe(3);
    });

    it('guards the roles page with the permission the backend checks', function (): void {
        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        $backendPermission = File::get(
            __DIR__ . '/../../src/Stubs/LaravelPermissions/Permissions/RolePermissions.stub'
        );

        expect(File::get($this->root . '/src/services/permissions/constants.ts'))
            ->toContain('manageRoles: "roles.manage"')
            ->and($backendPermission)->toContain("public const MANAGE = 'roles.manage';")
            ->and(File::get($this->root . '/src/routes/_private/roles/page.tsx'))
            ->toContain('createFileRoute("/_private/roles/")')
            ->toContain('return ensurePermission(PERMISSIONS.manageRoles);');
    });
});
