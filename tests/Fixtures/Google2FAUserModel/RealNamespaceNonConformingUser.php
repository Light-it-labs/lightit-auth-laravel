<?php

declare(strict_types=1);

// Deliberately NOT namespaced under `Lightitlabs\Tests\` and not autoloaded via
// PSR-4 - this declares the package's own real, hardcoded
// `Lightit\Users\Domain\Models\User` FQCN (see Google2FAInstaller::USER_MODEL_CLASS),
// the same one every generated 2FA stub assumes. It exists so
// Google2FAInstallerUserModelWarningTest can prove the guard's behaviour
// against that exact real class name - not a fixture double - without
// pulling in a full consumer app. Required manually (never autoloaded) so
// only one test file ever triggers this one-time class declaration for the
// whole suite; it must never extend TwoFactorAuthenticatable, so keep this
// file empty of any parent class.

namespace Lightit\Users\Domain\Models;

class User {}
