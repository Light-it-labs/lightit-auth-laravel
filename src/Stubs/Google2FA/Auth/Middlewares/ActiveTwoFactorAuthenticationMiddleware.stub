<?php

declare(strict_types=1);

namespace Lightit\Shared\App\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Lightit\Shared\App\Exceptions\Http\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

final readonly class ActiveTwoFactorAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('google2fa.mandatory') && ! config('google2fa.enabled')) {
            return $next($request);
        }

        $guardName = config('google2fa.guard');

        /** @var \Lightit\Authentication\Domain\TwoFactorAuthenticatable  $user */
        $user = $request->user($guardName);

        if (config('google2fa.mandatory') || $user->hasTwoFactorAuthenticationEnabled()) {
            if (! $user->hasTwoFactorAuthenticationConfigured()) {
                throw new UnauthorizedException(__('google2fa.2fa_not_configured'));
            }

            if ($user->hasTwoFactorAuthenticationExpired()) {
                throw new UnauthorizedException(__('google2fa.2fa_expired'));
            }
        }

        return $next($request);
    }
}
