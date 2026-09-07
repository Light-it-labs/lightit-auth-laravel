<?php

declare(strict_types=1);

use Lightitlabs\Tools\RouteFileRegistrar;
use Lightitlabs\Tools\RouteRegistrationOutcome;

describe('RouteFileRegistrar', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir().'/route-file-registrar-'.uniqid();
        mkdir($this->directory, 0755, true);
        $this->parent = $this->directory.'/api.php';
        $this->registrar = new RouteFileRegistrar;
    });

    afterEach(function (): void {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    });

    describe('marker', function (): void {
        it('builds a marker comment scoped to the given label', function (): void {
            expect($this->registrar->marker('authentication'))
                ->toBe('// lightit-auth: authentication routes');
        });
    });

    describe('requireStatement', function (): void {
        it('builds a single-quoted require with no space around the concatenation dot', function (): void {
            expect($this->registrar->requireStatement('auth.php'))
                ->toBe("require __DIR__.'/auth.php';");
        });
    });

    describe('register', function (): void {
        it('appends the marker and require statement to a fresh parent file', function (): void {
            file_put_contents($this->parent, "<?php\n\ndeclare(strict_types=1);\n");

            $outcome = $this->registrar->register($this->parent, 'auth.php', 'authentication');

            expect($outcome)->toBe(RouteRegistrationOutcome::Registered)
                ->and((string) file_get_contents($this->parent))->toBe(
                    "<?php\n\ndeclare(strict_types=1);\n\n"
                    ."// lightit-auth: authentication routes\n"
                    ."require __DIR__.'/auth.php';\n"
                );
        });

        it('trims trailing whitespace before appending', function (): void {
            file_put_contents($this->parent, "<?php\n\ndeclare(strict_types=1);\n\n\n   \n");

            $this->registrar->register($this->parent, 'auth.php', 'authentication');

            expect((string) file_get_contents($this->parent))->toBe(
                "<?php\n\ndeclare(strict_types=1);\n\n"
                ."// lightit-auth: authentication routes\n"
                ."require __DIR__.'/auth.php';\n"
            );
        });

        it('is idempotent once the marker is already present', function (): void {
            file_put_contents(
                $this->parent,
                "<?php\n\n// lightit-auth: authentication routes\nrequire __DIR__.'/auth.php';\n"
            );
            $before = (string) file_get_contents($this->parent);

            $outcome = $this->registrar->register($this->parent, 'auth.php', 'authentication');

            expect($outcome)->toBe(RouteRegistrationOutcome::AlreadyRegistered)
                ->and((string) file_get_contents($this->parent))->toBe($before);
        });

        it('registers two independently-marked route files without colliding', function (): void {
            file_put_contents($this->parent, "<?php\n");

            $this->registrar->register($this->parent, 'auth.php', 'authentication');
            $outcome = $this->registrar->register($this->parent, 'roles.php', 'roles and permissions');

            expect($outcome)->toBe(RouteRegistrationOutcome::Registered)
                ->and((string) file_get_contents($this->parent))->toBe(
                    "<?php\n\n"
                    ."// lightit-auth: authentication routes\n"
                    ."require __DIR__.'/auth.php';\n\n"
                    ."// lightit-auth: roles and permissions routes\n"
                    ."require __DIR__.'/roles.php';\n"
                );
        });

        it('reports ParentMissing without creating the parent file', function (): void {
            $outcome = $this->registrar->register($this->parent, 'auth.php', 'authentication');

            expect($outcome)->toBe(RouteRegistrationOutcome::ParentMissing)
                ->and($this->parent)->not->toBeFile();
        });

    });

    describe('shadowedRoutes', function (): void {
        $patterns = [
            'GET me' => '/(?:Route::|->)get\(\s*[\'"]\/?me[\'"]/',
            'POST auth/login' => '/(?:Route::|->)post\(\s*[\'"]\/?auth\/login[\'"]/',
        ];

        it('returns the labels of every pattern that matches', function () use ($patterns): void {
            file_put_contents($this->parent, "<?php\n\nRoute::get('/me', fn () => null);\n");

            expect($this->registrar->shadowedRoutes($this->parent, $patterns))->toBe(['GET me']);
        });

        it('returns an empty list when nothing matches', function () use ($patterns): void {
            file_put_contents($this->parent, "<?php\n\nRoute::get('/health', fn () => null);\n");

            expect($this->registrar->shadowedRoutes($this->parent, $patterns))->toBe([]);
        });

        it('returns an empty list when the parent file does not exist', function () use ($patterns): void {
            expect($this->registrar->shadowedRoutes($this->parent, $patterns))->toBe([]);
        });
    });
});
