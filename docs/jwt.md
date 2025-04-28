## JWT

- Secure login using email and password.
- Token refresh mechanism to extend user sessions seamlessly.
- Logout functionality with optional token blacklisting, configurable based on project needs.

1. Update your `User` model to implement the `JWTSubject` contract and define two methods: `getJWTIdentifier()` and `getJWTCustomClaims()`.

```php
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
````

2. Create the routes for the methods provided by the package.

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

3. **Update environment configuration**

- In your `.env`:

```dotenv
AUTH_GUARD=api
```

- In `config/auth.php`:

```php
'api' => [
    'driver' => 'jwt',
    'provider' => 'users',
],
```
---
