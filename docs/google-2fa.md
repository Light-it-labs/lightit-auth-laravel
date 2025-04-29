## Google Two-Factor Authentication (2FA)

Enhance account security by enabling Two-Factor Authentication (2FA) using [antonioribeiro/google2fa-laravel](https://github.com/antonioribeiro/google2fa-laravel).

> [!NOTE]
> 2FA support is included at the structural level, but enforcement is not applied by default.

> [!TIP]
> You're free to define how and when 2FA is enforced. Common strategies include two-step login, single-step login, 2FA on sensitive actions, or trusted device recognition. The exact behavior will depend on your project’s requirements.

> This package provides the base structure and middleware to support 2FA. Implementation details—such as 2FA token lifetime, logout behavior, or device trust logic—must be defined within your application logic.

### Setup

#### 1. Install the package

The 2FA package is automatically installed when selected during `auth:setup`.

#### 2. Extend your User model

Your `User` model must extend `TwoFactorAuthenticatable`:

```php
<?php

use Lightit\Authentication\Domain\TwoFactorAuthenticatable;

class User extends TwoFactorAuthenticatable
{
    // Your traits and methods
}
```

#### 3. Update casts

Add the following casts to your model to ensure proper encryption and date handling:

```php
protected function casts(): array
{
    return [
        // ...
        self::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME => 'encrypted',
        self::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME => 'datetime',
    ];
}
```

#### 4. Define 2FA-related routes

```php
use Lightit\Authentication\App\Controllers\SetupTwoFactorAuthenticationController;
use Lightit\Authentication\App\Controllers\EnableTwoFactorAuthenticationController;
use Lightit\Authentication\App\Controllers\DisableTwoFactorAuthenticationController;
use Lightit\Shared\App\Middlewares\ActiveTwoFactorAuthenticationMiddleware;
use Lightit\Shared\App\Middlewares\InactiveTwoFactorAuthenticationMiddleware;

Route::middleware(['auth', InactiveTwoFactorAuthenticationMiddleware::class])
    ->prefix('2fa')
    ->name('2fa.')
    ->group(static function () {
        Route::post('setup', SetupTwoFactorAuthenticationController::class)->name('setup');
        Route::post('enable', EnableTwoFactorAuthenticationController::class)->name('enable');
    });

Route::middleware(['auth', ActiveTwoFactorAuthenticationMiddleware::class])
    ->prefix('2fa')
    ->name('2fa.')
    ->group(static function () {
        Route::post('disable', DisableTwoFactorAuthenticationController::class)->name('disable');
    });
```

---
