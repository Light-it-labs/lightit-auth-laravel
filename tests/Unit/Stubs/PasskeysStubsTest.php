<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Lightitlabs\Auth\Installers\PasskeysInstaller;

function passkeyStub(string $relative): string
{
    return (string) file_get_contents(PasskeysInstaller::stubDirectory() . '/' . $relative);
}

describe('Passkeys backend stubs', function (): void {
    it('keeps every web-auth/webauthn-lib import inside PasskeyCeremonyService', function (): void {
        foreach (File::allFiles(PasskeysInstaller::stubDirectory()) as $file) {
            $importsLibrary = preg_match('/^use (Webauthn|Cose|ParagonIE)\\\\/m', $file->getContents()) === 1;

            expect($importsLibrary)->toBe($file->getFilename() === 'PasskeyCeremonyService.stub', $file->getPathname());
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

    it(
        'stores the credential id base64url with a fixed-length unique hash, and explicit timestamps',
        function (): void {
            expect(passkeyStub('database/migrations/create_passkeys_table.stub'))
                ->toContain("\$table->foreignId('user_id')->constrained()->cascadeOnDelete();")
                ->toContain("\$table->char('credential_id_hash', 64)->unique();")
                ->toContain("\$table->text('credential_id');")
                ->toContain("\$table->timestamp('last_used_at')->nullable();")
                ->not->toContain('timestamps()');
    
            expect(passkeyStub('Auth/Services/PasskeyCeremonyService.stub'))
                ->toContain("credentialIdHash: hash('sha256', \$record->publicKeyCredentialId)")
                ->toContain('credentialId: Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId)');
        }
    );

    it('derives the user handle from an HMAC keyed with app.key', function (): void {
        expect(passkeyStub('Auth/Services/PasskeyCeremonyService.stub'))
            ->toContain("hash_hmac('sha256', 'passkey-user:' . \$user->id, Config::string('app.key'), true)")
            ->not->toContain('id: random_bytes')
            ->toContain('excludeCredentials: $this->registeredCredentials($user)');
    });

    it('turns every ceremony failure into the dedicated 422 instead of a 500', function (): void {
        expect(passkeyStub('Auth/Services/PasskeyCeremonyService.stub'))
            ->toContain('} catch (Throwable) {')
            ->toContain('throw new PasskeyRegistrationFailedException();');

        expect(passkeyStub('Auth/Exceptions/PasskeyRegistrationFailedException.stub'))->toContain('$status = 422;');
        expect(passkeyStub('Auth/Exceptions/PasskeyChallengeExpiredException.stub'))->toContain('$status = 410;');
        expect(passkeyStub('Auth/Exceptions/PasskeyAlreadyRegisteredException.stub'))->toContain('$status = 409;');
    });

    it('asks for the current password to start a registration and to delete a passkey', function (): void {
        foreach (['StartPasskeyRegistrationRequest', 'DeletePasskeyRequest'] as $request) {
            expect(passkeyStub("Auth/Requests/{$request}.stub"))
                ->toContain("self::PASSWORD => ['required', 'string', 'current_password:web']");
        }
    });

    it(
        'authorizes rename and delete against the owner through #[CurrentUser] and #[RouteParameter]',
        function (): void {
            foreach (['RenamePasskeyRequest', 'DeletePasskeyRequest'] as $request) {
                expect(passkeyStub("Auth/Requests/{$request}.stub"))
                    ->toContain("#[RouteParameter('passkey')]")
                    ->toContain('#[CurrentUser]')
                    ->toContain('return $passkey->user_id === $user->id;')
                    ->not->toContain('__construct');
            }
        }
    );

    it('answers 201 on store and 204 on delete', function (): void {
        expect(passkeyStub('Auth/Controllers/StorePasskeyController.stub'))
            ->toContain('->setStatusCode(JsonResponse::HTTP_CREATED)');
        expect(passkeyStub('Auth/Controllers/DeletePasskeyController.stub'))
            ->toContain('return response()->noContent();');
    });

    it('puts every route behind auth:sanctum and throttles everything but the list', function (): void {
        $routes = passkeyStub('routes/passkeys.stub');

        expect($routes)
            ->toContain("->middleware('auth:sanctum')")
            ->toContain("Route::get('/', ListPasskeysController::class);")
            ->toContain("Route::post('/', StorePasskeyController::class)->middleware('throttle:passkeys');")
            ->toContain(
                "Route::post('registration-options', StartPasskeyRegistrationController::class)->middleware('throttle:passkeys');"
            )
            ->toContain("->whereNumber('passkey')");
    });

    it('writes every model through property assignment and *OrFail', function (): void {
        foreach (File::files(PasskeysInstaller::stubDirectory() . '/Auth/Actions') as $file) {
            expect($file->getContents())
                ->not->toMatch('/->(create|update|insert|firstOrCreate|updateOrCreate)\(/')
                ->not->toMatch('/->(save|delete)\(\)/');
        }
    });
});
