<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeLaravelPermissionFrontendCommand;

describe('LaravelPermissionFrontendInstaller', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/lightit-permissions-frontend-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/react-project', $this->root);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('writes every permissions service file and the TODO doc into the resolved frontend root', function (): void {
        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand($this->root));

        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        foreach ([
            'src/services/permissions/constants.ts',
            'src/services/permissions/use-permissions.ts',
            'src/services/permissions/ensure-permission.ts',
            'ROLES-PERMISSIONS-FRONTEND-TODO.md',
        ] as $relative) {
            expect($this->root.'/'.$relative)->toBeFile();
        }

        expect(file_get_contents($this->root.'/src/services/permissions/constants.ts'))
            ->toBe(file_get_contents(
                __DIR__.'/../Fixtures/frontend/expected/src/services/permissions/constants.ts'
            ));
    });

    it('reports every dependency already installed when the fixture project has them all', function (): void {
        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand($this->root));

        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/ROLES-PERMISSIONS-FRONTEND-TODO.md'))
            ->toContain('Every dependency this layer needs is already installed.');
    });

    it('reports missing dependencies with the install command for the detected package manager', function (): void {
        File::put($this->root.'/package.json', json_encode([
            'name' => 'sample',
            'private' => true,
            'dependencies' => ['react' => '^19.2.3'],
        ]));

        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand($this->root));

        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/ROLES-PERMISSIONS-FRONTEND-TODO.md'))
            ->toContain('Missing dependencies. Run:')
            ->toContain('pnpm add @tanstack/react-query @tanstack/react-router');
    });

    it('surfaces the existing inline permission checks it finds as a migration list', function (): void {
        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand($this->root));

        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/ROLES-PERMISSIONS-FRONTEND-TODO.md'))
            ->toContain('src/routes/manage-roles.tsx');
    });

    it('flags a missing current-user query factory with a repoint note', function (): void {
        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand($this->root));

        $this->artisan('laravel-permission-frontend-fake')->assertSuccessful();

        expect(file_get_contents($this->root.'/ROLES-PERMISSIONS-FRONTEND-TODO.md'))
            ->toContain('No `src/services/auth/factories.ts` was found');
    });

    it('warns and skips instead of failing when no React project resolves', function (): void {
        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand);

        $this->artisan('laravel-permission-frontend-fake')
            ->expectsOutputToContain('No React project found next to the application.')
            ->assertSuccessful();
    });

    it('throws with the rejection reason when an explicit frontend path is not a React project', function (): void {
        $apiRoot = sys_get_temp_dir().'/lightit-permissions-api-'.bin2hex(random_bytes(6));
        File::copyDirectory(__DIR__.'/../Fixtures/frontend/api-project', $apiRoot);

        Artisan::registerCommand(new FakeLaravelPermissionFrontendCommand($apiRoot));

        expect(fn () => $this->artisan('laravel-permission-frontend-fake'))
            ->toThrow(RuntimeException::class, 'Rejected --frontend-path');

        File::deleteDirectory($apiRoot);
    });
});
