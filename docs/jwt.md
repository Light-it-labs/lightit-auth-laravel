## JSON Web Token (JWT) Authentication

This module enables stateless authentication using [php-open-source-saver/jwt-auth](https://github.com/PHP-Open-Source-Saver/jwt-auth), allowing secure login, token refresh, and logout handling for APIs.

> [!NOTE]
> JWT is best suited for stateless APIs where session handling is delegated entirely to the client via tokens.

> [!TIP]
> The package supports refresh tokens, token invalidation, and optional blacklisting. Final behavior should be tailored to your project: you decide when to refresh tokens, expire them, or invalidate on logout.

### Setup

#### 1. Update your `User` model

Implement the `JWTSubject` contract and define the required methods:

```php
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
```

#### 2. Define authentication routes

```php
use Illuminate\Support\Facades\Route;
use Lightit\Authentication\App\Controllers\LoginController;
use Lightit\Authentication\App\Controllers\LogoutController;
use Lightit\Authentication\App\Controllers\RefreshController;

Route::prefix('auth')->group(static function () {
    Route::post('login', LoginController::class);
    Route::post('logout', LogoutController::class);
    Route::post('refresh', RefreshController::class);
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
