<?php

declare(strict_types=1);

use Lightitlabs\Tools\EnvironmentKeys;

describe('EnvironmentKeys', function (): void {
    beforeEach(function (): void {
        $this->keys = new EnvironmentKeys;
    });

    it('does not find a key that is absent', function (): void {
        expect($this->keys->isSet("APP_ENV=local\n", 'SESSION_SAME_SITE'))->toBeFalse();
    });

    it('finds a key that is set', function (): void {
        expect($this->keys->isSet("APP_ENV=local\nSESSION_SAME_SITE=lax\n", 'SESSION_SAME_SITE'))
            ->toBeTrue();
    });

    it('finds a key that is set behind leading whitespace', function (): void {
        expect($this->keys->isSet("  SESSION_SAME_SITE=lax\n", 'SESSION_SAME_SITE'))->toBeTrue();
    });

    it('treats a commented key as documentation, not configuration', function (): void {
        expect($this->keys->isSet("# SESSION_SAME_SITE=lax\n", 'SESSION_SAME_SITE'))->toBeFalse();
    });

    it('ignores the production examples the installer writes into its own block', function (): void {
        // A commented-out example block an installer might append - these once suppressed the real variables.
        $block = <<<'ENV'
            # Production: SPA on example.com, API on api.example.com.
            #   SESSION_DOMAIN=.example.com
            #   SESSION_SECURE_COOKIE=true
            #   SESSION_SAME_SITE=lax
            #   SANCTUM_STATEFUL_DOMAINS=example.com
            #   CORS_SUPPORTS_CREDENTIALS=true
            ENV;

        expect($this->keys->isSet($block, 'SANCTUM_STATEFUL_DOMAINS'))->toBeFalse()
            ->and($this->keys->isSet($block, 'CORS_SUPPORTS_CREDENTIALS'))->toBeFalse()
            ->and($this->keys->isSet($block, 'SESSION_SECURE_COOKIE'))->toBeFalse();
    });

    it('does not confuse a key with one that merely shares its prefix', function (): void {
        expect($this->keys->isSet("SESSION_SAME_SITE_EXTRA=1\n", 'SESSION_SAME_SITE'))->toBeFalse();
    });

    it('parses an assignment into a key and a value', function (): void {
        expect($this->keys->parse('SANCTUM_STATEFUL_DOMAINS=localhost:5173'))
            ->toBe(['key' => 'SANCTUM_STATEFUL_DOMAINS', 'value' => 'localhost:5173']);
    });

    it('does not parse comments or blank lines', function (): void {
        expect($this->keys->parse('# a comment'))->toBeNull()
            ->and($this->keys->parse(''))->toBeNull()
            ->and($this->keys->parse('#   SESSION_SAME_SITE=lax'))->toBeNull();
    });
});
