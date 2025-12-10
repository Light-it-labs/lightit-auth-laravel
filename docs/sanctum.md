## Sanctum API Token Authentication

This option provides simple token-based API authentication using (Laravel Sanctum)[https://laravel.com/docs/master/sanctum].

> Projects bootstrapped for this package already include Sanctum by default.

### Minimal Setup

#### 1. User model

Add the `HasApiTokens` trait to your `User` model:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

#### 2. Define authentication routes

```php
use Illuminate\Support\Facades\Route;
use Lightit\Authentication\App\Controllers\{LoginController, LogoutController};

Route::prefix('auth')->group(static function () {
    Route::post('login', LoginController::class);
    Route::post('logout', LogoutController::class);
});
```

#### 3. Update environment and config

In your `.env` file:

```dotenv
AUTH_GUARD=api
```

In `config/auth.php`, update the API guard:

```php
'guards' => [
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

---
