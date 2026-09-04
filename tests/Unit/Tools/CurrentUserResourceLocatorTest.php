<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Lightitlabs\Tools\CurrentUserResourceLocator;

describe('CurrentUserResourceLocator', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/current-user-resource-locator-'.uniqid();
        mkdir($this->root, 0755, true);
        $this->locator = new CurrentUserResourceLocator;
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    $jsonResource = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Http\Resources;

        use Illuminate\Http\Request;
        use Illuminate\Http\Resources\Json\JsonResource;

        class UserResource extends JsonResource
        {
            public function toArray(Request $request): array
            {
                return [
                    'id' => $this->id,
                ];
            }
        }

        PHP;

    $writeCandidate = function (string $root, string $relative, string $contents): void {
        $path = $root.'/'.$relative;
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);
    };

    it('finds app/Http/Resources/UserResource.php first', function () use ($jsonResource, $writeCandidate): void {
        $writeCandidate($this->root, 'app/Http/Resources/UserResource.php', $jsonResource);
        $writeCandidate($this->root, 'app/Http/Resources/CurrentUserResource.php', $jsonResource);
        $writeCandidate($this->root, 'src/Users/App/Resources/UserResource.php', $jsonResource);

        expect($this->locator->locate($this->root))
            ->toBe($this->root.'/app/Http/Resources/UserResource.php');
    });

    it('falls back to app/Http/Resources/CurrentUserResource.php', function () use ($jsonResource, $writeCandidate): void {
        $writeCandidate($this->root, 'app/Http/Resources/CurrentUserResource.php', $jsonResource);
        $writeCandidate($this->root, 'src/Users/App/Resources/UserResource.php', $jsonResource);

        expect($this->locator->locate($this->root))
            ->toBe($this->root.'/app/Http/Resources/CurrentUserResource.php');
    });

    it('falls back to src/Users/App/Resources/UserResource.php last', function () use ($jsonResource, $writeCandidate): void {
        $writeCandidate($this->root, 'src/Users/App/Resources/UserResource.php', $jsonResource);

        expect($this->locator->locate($this->root))
            ->toBe($this->root.'/src/Users/App/Resources/UserResource.php');
    });

    it('returns null when no candidate matches', function (): void {
        expect($this->locator->locate($this->root))->toBeNull();
    });

    it('does not accept a file that is missing toArray or JsonResource', function () use ($writeCandidate): void {
        $writeCandidate($this->root, 'app/Http/Resources/UserResource.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Http\Resources;

            class UserResource
            {
                public function toSomethingElse(): array
                {
                    return [];
                }
            }

            PHP);

        expect($this->locator->locate($this->root))->toBeNull();
    });

    it('checks only the explicit path when one is given, ignoring the ordered candidates', function () use (
        $jsonResource,
        $writeCandidate
    ): void {
        $writeCandidate($this->root, 'app/Http/Resources/UserResource.php', $jsonResource);
        $writeCandidate($this->root, 'custom/MyResource.php', $jsonResource);

        expect($this->locator->locate($this->root, $this->root.'/custom/MyResource.php'))
            ->toBe($this->root.'/custom/MyResource.php');
    });

    it('returns null for an explicit path that does not resolve', function (): void {
        expect($this->locator->locate($this->root, $this->root.'/absent/MyResource.php'))
            ->toBeNull();
    });

    describe('rejectionReason', function () use ($writeCandidate): void {
        it('reports a missing file', function (): void {
            expect($this->locator->rejectionReason($this->root.'/absent.php'))
                ->toBe("file does not exist: {$this->root}/absent.php");
        });

        it('reports an existing file that does not look like a JsonResource', function () use ($writeCandidate): void {
            $writeCandidate($this->root, 'NotAResource.php', "<?php\n\nclass NotAResource {}\n");

            expect($this->locator->rejectionReason($this->root.'/NotAResource.php'))
                ->toBe("exists but does not look like a JsonResource with toArray: {$this->root}/NotAResource.php");
        });
    });
});
