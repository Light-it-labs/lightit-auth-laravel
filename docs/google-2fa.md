## Google Two-Factor Authentication (2FA)

Enhance account security by enabling Two-Factor Authentication (2FA) using [antonioribeiro/google2fa-laravel](https://github.com/antonioribeiro/google2fa-laravel).

> [!NOTE]
> Two-Factor Authentication (2FA) support is included at the structural level.
> However, 2FA enforcement at login is not applied by default.
> Projects integrating this package are responsible for implementing 2FA verification flow after credentials validation if required.
>
> Different strategies (two-step login, single-step login, 2FA on sensitive actions, trusted devices) can be implemented depending on project needs.
> The starter kit provides the base structure, but the final behavior must be customized per application.

### Setup

1. **Install the package**

The 2FA package is automatically installed when selected during `auth:setup`.

2. **Extend your User model**

Your `User` model must extend `TwoFactorAuthenticatable`:

```php
<?php

use Lightit\Authentication\Domain\TwoFactorAuthenticatable;

class User extends TwoFactorAuthenticatable
{
    // Your traits and methods
}
```

3. **Update casts**

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

4. **Define 2FA-related routes**

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

[//]: # (# Two-Factor Authentication Usage Scenarios)

[//]: # ()
[//]: # (This package provides the base infrastructure for 2FA, but projects can implement different strategies depending on their needs:)

[//]: # ()
[//]: # (| Model | Description | Pros | Cons | Recommended Usage |)

[//]: # (|:---|:---|:---|:---|:---|)

[//]: # (| **Two-step login &#40;login ➔ then 2FA&#41;** | Validate credentials first, then prompt for 2FA code. | Flexible, scalable | Slightly more complex frontend/backend handling | ✅ Most applications |)

[//]: # (| **Single-step login &#40;email + password + 2FA together&#41;** | Validate credentials and 2FA code in one request. | Simpler UI flow | Less control, harder to manage retries separately | Small/simple projects |)

[//]: # (| **2FA challenge on sensitive actions only** | Prompt for 2FA only when performing critical operations &#40;e.g., payments, account changes&#41;. | Better UX | Login is less protected | Apps with non-critical login |)

[//]: # (| **Trusted devices &#40;Remember device&#41;** | Skip 2FA after first success on trusted devices for a set time. | Great UX | Risk if device security is weak | Apps with frequent logins, e.g., e-commerce |)
