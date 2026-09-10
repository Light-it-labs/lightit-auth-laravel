<?php

declare(strict_types=1);

use Lightitlabs\Tools\LoginActionPatcher;
use Lightitlabs\Tools\LoginActionPatchOutcome;
use Lightitlabs\Tools\OriginMarker;
use Lightitlabs\Tools\StubCopier;

describe('LoginActionPatcher', function (): void {
    beforeEach(function (): void {
        $this->directory = sys_get_temp_dir().'/login-action-patcher-'.uniqid();
        mkdir($this->directory, 0755, true);

        $this->plainStub = $this->directory.'/LoginAction.plain.stub';
        $this->pipelineStub = $this->directory.'/LoginAction.pipeline.stub';
        $this->destination = $this->directory.'/LoginAction.php';

        file_put_contents($this->plainStub, "<?php\n\n// plain sanctum login\n");
        file_put_contents($this->pipelineStub, "<?php\n\n// 2fa pipeline login\n");

        $this->stubCopier = new StubCopier(new OriginMarker('0.0.0-test'));
        $this->patcher = new LoginActionPatcher;
    });

    afterEach(function (): void {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    });

    it('installs the pipeline stub fresh when nothing exists at the destination yet', function (): void {
        $outcome = $this->patcher->install(
            $this->stubCopier,
            $this->plainStub,
            $this->pipelineStub,
            $this->destination,
        );

        expect($outcome)->toBe(LoginActionPatchOutcome::Installed)
            ->and((string) file_get_contents($this->destination))->toContain('2fa pipeline login');
    });

    it('replaces an untouched Sanctum LoginAction.php with the 2FA pipeline version', function (): void {
        $this->stubCopier->copy($this->plainStub, $this->destination);

        $outcome = $this->patcher->install(
            $this->stubCopier,
            $this->plainStub,
            $this->pipelineStub,
            $this->destination,
        );

        expect($outcome)->toBe(LoginActionPatchOutcome::Patched)
            ->and((string) file_get_contents($this->destination))->toContain('2fa pipeline login');
    });

    it('does not touch a LoginAction.php a consumer edited after Sanctum installed it', function (): void {
        $this->stubCopier->copy($this->plainStub, $this->destination);
        file_put_contents($this->destination, '// customized by the consuming project'.PHP_EOL);

        $outcome = $this->patcher->install(
            $this->stubCopier,
            $this->plainStub,
            $this->pipelineStub,
            $this->destination,
        );

        expect($outcome)->toBe(LoginActionPatchOutcome::CustomizedSkipped)
            ->and((string) file_get_contents($this->destination))->toBe('// customized by the consuming project'.PHP_EOL);
    });

    it('is idempotent when the pipeline version is already installed', function (): void {
        $this->stubCopier->copy($this->plainStub, $this->destination);
        $this->patcher->install($this->stubCopier, $this->plainStub, $this->pipelineStub, $this->destination);

        $secondOutcome = $this->patcher->install(
            $this->stubCopier,
            $this->plainStub,
            $this->pipelineStub,
            $this->destination,
        );

        expect($secondOutcome)->toBe(LoginActionPatchOutcome::AlreadyApplied);
    });

    it('reports a missing pipeline source instead of touching the destination', function (): void {
        $this->stubCopier->copy($this->plainStub, $this->destination);

        $outcome = $this->patcher->install(
            $this->stubCopier,
            $this->plainStub,
            $this->directory.'/does-not-exist.stub',
            $this->destination,
        );

        expect($outcome)->toBe(LoginActionPatchOutcome::SourceMissing)
            ->and((string) file_get_contents($this->destination))->toContain('plain sanctum login');
    });
});
