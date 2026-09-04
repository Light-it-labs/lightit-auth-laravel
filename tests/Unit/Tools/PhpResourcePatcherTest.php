<?php

declare(strict_types=1);

use Lightitlabs\Tools\PhpResourcePatcher;
use Lightitlabs\Tools\PhpResourcePatchOutcome;

describe('PhpResourcePatcher', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir().'/php-resource-patcher-'.uniqid();
        mkdir($this->directory, 0755, true);
        $this->path = $this->directory.'/UserResource.php';
    });

    afterEach(function (): void {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    });

    $userResource = <<<'PHP'
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
                    'name' => $this->name,
                    'email' => $this->email,
                ];
            }
        }

        PHP;

    it('patches a realistic toArray() body, landing the marker and both entries inside the return array', function () use (
        $userResource
    ): void {
        file_put_contents($this->path, $userResource);

        expect((new PhpResourcePatcher)->addRolesAndPermissions($this->path))
            ->toBe(PhpResourcePatchOutcome::Patched);

        expect((string) file_get_contents($this->path))->toBe(<<<'PHP'
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
                        // lightit-auth: roles and permissions
                        'roles' => $this->roles->pluck('name')->all(),
                        'permissions' => $this->getAllPermissions()->pluck('name')->all(),
                        'id' => $this->id,
                        'name' => $this->name,
                        'email' => $this->email,
                    ];
                }
            }

            PHP);
    });

    it('leaves an already patched file untouched', function () use ($userResource): void {
        file_put_contents($this->path, $userResource);
        $patcher = new PhpResourcePatcher;
        $patcher->addRolesAndPermissions($this->path);
        $patched = file_get_contents($this->path);

        expect($patcher->addRolesAndPermissions($this->path))
            ->toBe(PhpResourcePatchOutcome::AlreadyApplied);

        expect(file_get_contents($this->path))->toBe($patched);
    });

    it('reports a conflicting key and writes nothing when toArray() already declares roles', function (): void {
        $withRolesKey = <<<'PHP'
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
                        'roles' => $this->roleNames,
                    ];
                }
            }

            PHP;
        file_put_contents($this->path, $withRolesKey);

        expect((new PhpResourcePatcher)->addRolesAndPermissions($this->path))
            ->toBe(PhpResourcePatchOutcome::KeyAlreadyPresent);

        expect(file_get_contents($this->path))->toBe($withRolesKey);
    });

    it('reports the anchor as missing and writes nothing when there is no toArray method', function (): void {
        $withoutToArray = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Http\Resources;

            use Illuminate\Http\Resources\Json\JsonResource;

            class UserResource extends JsonResource
            {
                public function toOther(): array
                {
                    return [
                        'id' => $this->id,
                    ];
                }
            }

            PHP;
        file_put_contents($this->path, $withoutToArray);

        expect((new PhpResourcePatcher)->addRolesAndPermissions($this->path))
            ->toBe(PhpResourcePatchOutcome::AnchorNotFound);

        expect(file_get_contents($this->path))->toBe($withoutToArray);
    });

    it('reports a missing file', function (): void {
        expect((new PhpResourcePatcher)->addRolesAndPermissions($this->directory.'/absent.php'))
            ->toBe(PhpResourcePatchOutcome::Missing);
    });

    it('needs a manual step for every outcome that did not land the change', function (): void {
        expect(PhpResourcePatchOutcome::AnchorNotFound->needsManualStep())->toBeTrue()
            ->and(PhpResourcePatchOutcome::KeyAlreadyPresent->needsManualStep())->toBeTrue()
            ->and(PhpResourcePatchOutcome::Failed->needsManualStep())->toBeTrue()
            ->and(PhpResourcePatchOutcome::Corrupted->needsManualStep())->toBeTrue()
            ->and(PhpResourcePatchOutcome::Missing->needsManualStep())->toBeTrue()
            ->and(PhpResourcePatchOutcome::Patched->needsManualStep())->toBeFalse()
            ->and(PhpResourcePatchOutcome::AlreadyApplied->needsManualStep())->toBeFalse();
    });

    it('does not treat an unrelated mention of roles as an already applied patch', function (): void {
        $withUnrelatedComment = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Http\Resources;

            use Illuminate\Http\Request;
            use Illuminate\Http\Resources\Json\JsonResource;

            class UserResource extends JsonResource
            {
                // TODO: consider roles and permissions sometime later
                public function toArray(Request $request): array
                {
                    return [
                        'id' => $this->id,
                    ];
                }
            }

            PHP;
        file_put_contents($this->path, $withUnrelatedComment);
        $patcher = new PhpResourcePatcher;

        expect($patcher->addRolesAndPermissions($this->path))
            ->toBe(PhpResourcePatchOutcome::Patched);

        expect((string) file_get_contents($this->path))->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Http\Resources;

            use Illuminate\Http\Request;
            use Illuminate\Http\Resources\Json\JsonResource;

            class UserResource extends JsonResource
            {
                // TODO: consider roles and permissions sometime later
                public function toArray(Request $request): array
                {
                    return [
                        // lightit-auth: roles and permissions
                        'roles' => $this->roles->pluck('name')->all(),
                        'permissions' => $this->getAllPermissions()->pluck('name')->all(),
                        'id' => $this->id,
                    ];
                }
            }

            PHP);
    });

    it('reports the same text it applies', function () use ($userResource): void {
        file_put_contents($this->path, $userResource);
        $patcher = new PhpResourcePatcher;

        $patcher->addRolesAndPermissions($this->path);
        $written = (string) file_get_contents($this->path);

        // Whole-file comparison, not a toContain fragment: splices manualSnippet()'s
        // own return value into the untouched original, so this proves the applied
        // text and the reported text are identical, not merely that one contains
        // some fragment of the other.
        expect($written)->toBe(str_replace(
            "        return [\n",
            "        return [\n".$patcher->manualSnippet('            ')."\n",
            $userResource
        ));
    });
});
