<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Lightitlabs\Auth\Installers\PasskeysInstaller;

describe('Passkeys backend stubs', function (): void {
    it('imports only classes the installer writes or the boilerplate already has', function (): void {
        $known = [
            'Lightit\Users\Domain\Models\User',
            'Lightit\Shared\App\Exceptions\Http\HttpException',
            ...array_map(
                static fn (string $destination): string => 'Lightit\Authentication\\'
                    . str_replace(['/', '.php'], ['\\', ''], $destination),
                array_values(PasskeysInstaller::FILES),
            ),
        ];

        foreach (File::allFiles(PasskeysInstaller::stubDirectory()) as $file) {
            preg_match_all('/^use (Lightit\\\\[^;]+);/m', $file->getContents(), $imports);

            foreach ($imports[1] as $import) {
                expect($known)->toContain($import);
            }
        }
    });

    it('keeps every web-auth/webauthn-lib import inside PasskeyCeremonyService', function (): void {
        foreach (File::allFiles(PasskeysInstaller::stubDirectory()) as $file) {
            $importsLibrary = preg_match('/^use (Webauthn|Cose|ParagonIE)\\\\/m', $file->getContents()) === 1;

            expect($importsLibrary)->toBe($file->getFilename() === 'PasskeyCeremonyService.stub');
        }
    });

    it('never ships the removed Bearer login contract', function (): void {
        foreach (File::allFiles(PasskeysInstaller::stubDirectory()) as $file) {
            expect($file->getContents())
                ->not->toContain('LoginDto')
                ->not->toContain('LoginResource')
                ->not->toContain('CredentialsDto')
                ->not->toContain('Bearer');
        }
    });
});
