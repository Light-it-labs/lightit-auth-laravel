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
            'loginEndpoint' => 'auth/login',
            'loginRoutePath' => '/login',
            'logoutEndpoint' => 'auth/logout',
            'packageManager' => 'pnpm',
            'queryClientCheckbox' => 'x',
            'queryClientStatus' => 'Done automatically.',
            'queryKeyScope' => 'auth',
            'userSchemaFactory' => 'getUserSchema',
            'userSchemaImportPath' => '@/services/users/schemas',
            'userTypeImportPath' => '@/services/users/types',
            'zodMajor' => '4',
        ];
    }
}
