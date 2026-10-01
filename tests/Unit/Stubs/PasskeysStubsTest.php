<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Lightitlabs\Auth\Installers\PasskeysInstaller;
use Lightitlabs\Auth\Installers\SharedLoginFiles;

describe('Passkeys backend stubs', function (): void {
    it('imports only classes the installer writes or the boilerplate already has', function (): void {
        $known = [
            'Lightit\Users\Domain\Models\User',
            'Lightit\Users\App\Resources\UserResource',
            'Lightit\Shared\App\Exceptions\Http\HttpException',
            'Lightit\Shared\App\Exceptions\Http\UnauthenticatedException',
            ...array_map(
                static fn (string $destination): string => 'Lightit\Authentication\\'
                    . str_replace(['/', '.php'], ['\\', ''], $destination),
                [...array_values(PasskeysInstaller::FILES), ...array_values(SharedLoginFiles::FILES)],
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
                ->not->toMatch('/\b(LoginDto|LoginResource|CredentialsDto)\b/')
                ->not->toContain('Bearer');
        }
    });

    it('signs a passkey user in through the 2FA gate, never around it', function (): void {
        $action = (string) file_get_contents(
            PasskeysInstaller::stubDirectory() . '/Auth/Actions/PasskeyLoginAction.stub'
        );
        $controller = (string) file_get_contents(
            PasskeysInstaller::stubDirectory() . '/Auth/Controllers/PasskeyLoginController.stub'
        );

        expect($action)
            ->toContain('$this->loginByUserAction->execute($passkey->user);')
            ->not->toContain('executeAfterChallenge')
            ->and($controller)
            ->toContain('use Lightit\Users\App\Resources\UserResource;')
            ->toContain('return UserResource::make(');
    });
});
