## Sanctum API Token Authentication

This option provides simple token-based API authentication using Laravel Sanctum.

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
use Lightit\Authentication\App\Controllers\LoginController;
use Lightit\Authentication\App\Controllers\LogoutController;

Route::prefix('auth')->group(static function () {
    Route::post('login', LoginController::class)->name('login');
    Route::post('logout', LogoutController::class)->name('logout');
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
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

---
