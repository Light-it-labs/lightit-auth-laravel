<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Lightit\Authentication\Domain\DataTransferObjects\LoginDto;
use Lightit\Backoffice\Users\Domain\Models\User;
use Lightit\Shared\App\Exceptions\Http\InvalidGoogleTokenException;
use UnexpectedValueException;

class GoogleLoginAction
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly GoogleClient $googleClient,
    ) {
    }

    public function execute(string $googleIdToken): LoginDto
    {
        try {
            $payload = $this->googleClient->verifyIdToken($googleIdToken);
        } catch (UnexpectedValueException) {
            throw new InvalidGoogleTokenException('Invalid format');
        }

        if (! $payload) {
            throw new InvalidGoogleTokenException('The Google token is not valid');
        }

        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            [
                'name' => $payload['name'] ?? '',
                'password' => Hash::make(Str::random(10)),
            ]
        );

        return $this->loginAction->loginByUser($user);
    }
}
