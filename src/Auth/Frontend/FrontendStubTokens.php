<?php

declare(strict_types=1);

namespace Lightitlabs\Auth\Frontend;

final class FrontendStubTokens
{
    /**
     * Emitted as a whole regex body. Composing it from a path fragment inherits
     * an escaping bug: the leading slash of the fragment has to be escaped for
     * the regex but not for the path, so the two uses disagree.
     */
    public const API_SUFFIX_STRIP_REGEX = '/\/api$/';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'apiSuffixStripRegex' => self::API_SUFFIX_STRIP_REGEX,
            'apiCallSites' => '- None found.',
            'authStoreImporters' => '- None found.',
            'csrfCookieEndpoint' => '/sanctum/csrf-cookie',
            'currentUserEndpoint' => 'me',
            'currentUserResponseAccessor' => 'response.data.data',
            'dependencyReport' => 'Every dependency this layer needs is already installed.',
            'googleSsoLoginEndpoint' => 'auth/google',
            'loginEndpoint' => 'auth/login',
            'loginRoutePath' => '/login',
            'logoutEndpoint' => 'auth/logout',
            'packageManager' => 'pnpm',
            'queryClientCheckbox' => 'x',
            'queryClientStatus' => 'Done automatically.',
            'queryKeyScope' => 'auth',
            'twoFactorSetupEndpoint' => '2fa/setup',
            'twoFactorCompleteEndpoint' => '2fa/complete',
            'twoFactorVerifyRecoveryCodeEndpoint' => '2fa/verify-recovery-code',
            'twoFactorResetEndpoint' => '2fa/reset',
            'twoFactorDisableEndpoint' => '2fa/disable',
            'twoFactorRegenerateRecoveryCodesEndpoint' => '2fa/regenerate-recovery-codes',
            'twoFactorRequestResetEndpoint' => '2fa/request-reset',
            'userSchemaFactory' => 'getUserSchema',
            'userSchemaImportPath' => '@/services/users/schemas',
            'userTypeImportPath' => '@/services/users/types',
            'zodMajor' => '4',
        ];
    }
}
