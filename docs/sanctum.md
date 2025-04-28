## Sanctum API Token Authentication

This option provides simple token-based API authentication using Laravel Sanctum.

> Projects bootstrapped for this package already include Sanctum by default.

### Minimal Setup

1. Ensure the `HasApiTokens` trait is present in your User model:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

2. Define login and logout routes:

```php
use Lightit\Authentication\App\Controllers\LoginController;
use Lightit\Authentication\App\Controllers\LogoutController;

Route::prefix('auth')->group(static function () {
    Route::post('login', LoginController::class)->name('login');
    Route::post('logout', LogoutController::class)->name('logout');
});
```

3. Update environment configuration

- In your `.env`:

```dotenv
AUTH_GUARD=api
```

- In `config/auth.php`:

```php
'api' => [
    'driver' => 'sanctum',
    'provider' => 'users',
],
```
---
