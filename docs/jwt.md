### JWT

- Secure login using email and password.
- Token refresh mechanism to extend user sessions seamlessly.
- Logout functionality with optional token blacklisting, configurable based on project needs.

1. Update your `User` model to implement the `JWTSubject` contract and define two methods: `getJWTIdentifier()` and `getJWTCustomClaims()`.

```php
<?php

namespace App;

declare(strict_types=1);

use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
<?php

declare(strict_types=1);

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
- If you have also selected Google SSO, you need to add the following endpoint to validate the tokens from Google Authentication:
```php
<?php

use Illuminate\Support\Facades\Route;
use Lightit\Authentication\App\Controllers\GoogleLoginController;

Route::post('google', GoogleLoginController::class);

```

3. Update your environment configuration:

    - Add the following variables to your .env file:
    ```dotenv
    AUTH_GUARD=api
    AUTH_PASSWORD_BROKER=users
    ```
    - Update the guards array in the config/auth.php file to include the following configuration:
    ```php
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
    ```
