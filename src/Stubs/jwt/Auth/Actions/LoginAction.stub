<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Lightit\Authentication\Domain\DataTransferObjects\LoginDto;
use Lightit\Backoffice\Users\Domain\Models\User;
use Lightit\Shared\App\Exceptions\Http\UnauthorizedException;
use PHPOpenSourceSaver\JWTAuth\Factory as JWTAuth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

final class LoginAction
{
    public function __construct(
        private readonly AuthFactory $factory,
        private readonly JWTAuth $jwtAuth,
    ) {
    }

    /** @throws UnauthorizedException */
    public function execute(array $credentials): LoginDto
    {
        /** @var JWTGuard $guard */
        $guard = $this->factory->guard();

        if (! $token = $guard->attempt($credentials)) {
            throw new UnauthorizedException();
        }

        /** @var string $token */
        return new LoginDto(
            $token,
            'Bearer',
            $this->jwtAuth->getTTL() * 60,
        );
    }

    public function loginByUser(User $user): LoginDto
    {
        /** @var JWTGuard $guard */
        $guard = $this->factory->guard();

        /** @var string $token */
        $token = $guard->tokenById($user->getKey());

        return new LoginDto(
            $token,
            'bearer',
            $this->jwtAuth->getTTL() * 60,
        );
    }
}
