<?php

declare(strict_types=1);

namespace Lightit\Shared\Support\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Lightit\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthenticationInactivated
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

        if ($user->isTwoFactorAuthenticationConfigured() && ! $user->isTwoFactorAuthenticationExpired()) {
            throw new UnauthorizedException('Two-factor authentication is already configured.');
        }

        return $next($request);
    }
}
