<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeLaravelPermissionFrontendCommand;

/**
 * The react-template modules the generated files import, read from react-template@develop 8509eed;
 * re-check them when the template moves. Anything else under `@/` must be a file this installer writes.
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

    it('lists every i18n key the generated files use, with the same keys for en and es', function (): void {
        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        $todo = File::get($this->root . '/AUTH-ROLES-FRONTEND-TODO.md');
        [, $afterEnHeading] = explode('In `en.json`', $todo, 2);
        [$enSection, $esSection] = explode('In `es.json`', $afterEnHeading, 2);

        $keysIn = static function (string $section): array {
            preg_match_all('/```json\n(.*?)\n```/s', $section, $blocks);
            expect($blocks[1])->not->toBeEmpty();

            $keys = [];

            foreach ($blocks[1] as $block) {
                $decoded = json_decode(str_starts_with(trim($block), '{') ? $block : '{' . $block . '}', true);
                expect($decoded)->toBeArray();
                $keys = [...$keys, ...array_keys(Arr::dot($decoded))];
            }

            sort($keys);

            return $keys;
        };

        $en = $keysIn($enSection);
        $es = $keysIn($esSection);

        $used = [];

        foreach ($this->generatedFiles as $relative) {
            preg_match_all('/\bt\("([^"]+)"/', File::get($this->root . '/' . $relative), $matches);
            $used = [...$used, ...$matches[1]];
        }

        expect($used)->not->toBeEmpty()
            ->and($en)->toContain('navigation.links.roles')
            ->and($es)->toBe($en);

        foreach (array_unique($used) as $key) {
            expect([...$en, ...ROLES_TEMPLATE_I18N_KEYS])->toContain($key);
        }
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

        preg_match(
            "/MANAGE = '([^']+)'/",
            File::get(__DIR__ . '/../../src/Stubs/LaravelPermissions/Permissions/RolePermissions.stub'),
            $backendPermission,
        );

        expect(File::get($this->root . '/src/services/permissions/constants.ts'))
            ->toContain('manageRoles: "' . $backendPermission[1] . '"')
            ->and(File::get($this->root . '/src/routes/_private/roles/page.tsx'))
            ->toContain('createFileRoute("/_private/roles/")')
            ->toContain('return ensurePermission(PERMISSIONS.manageRoles);');
    });
});
