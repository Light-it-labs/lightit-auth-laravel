<?php

declare(strict_types=1);

namespace Lightit\Authentication\Domain\Actions;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Lightit\Authentication\Domain\DataTransferObjects\CredentialsDto;
use Lightit\Authentication\Domain\DataTransferObjects\LoginDto;
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

    /**
     * @throws UnauthorizedException
     */
    public function execute(CredentialsDto $credentials): LoginDto
    {
        /** @var JWTGuard $guard */
        $guard = $this->factory->guard();

        if (! $token = $guard->attempt($credentials->toArray())) {
            throw new UnauthorizedException();
        }

        /** @var string $token */
        return new LoginDto(
            accessToken: $token,
            tokenType: 'Bearer',
            expiresIn: $this->jwtAuth->getTTL() * 60,
        );
    }
}
