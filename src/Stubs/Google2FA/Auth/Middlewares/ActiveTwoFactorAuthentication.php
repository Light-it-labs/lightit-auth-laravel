<?php

declare(strict_types=1);

namespace Lightit\Shared\Support\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Lightit\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class ActiveTwoFactorAuthentication
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

        /** @var User $user */
        $user = $request->user();

        if (config('google2fa.mandatory') || $user->isTwoFactorAuthenticationEnabled()) {
            if (! $user->isTwoFactorAuthenticationConfigured()) {
                throw new UnauthorizedException('Two-factor authentication is not configured.');
            }

            if ($user->isTwoFactorAuthenticationExpired()) {
                throw new UnauthorizedException('Two-factor authentication is expired.');
            }
        }

        return $next($request);
    }
}
