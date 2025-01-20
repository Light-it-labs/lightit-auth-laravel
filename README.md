# Seamless Authentication Made Simple for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/light-it-labs/lightit-auth-laravel.svg?style=flat-square)](https://packagist.org/packages/light-it-labs/lightit-auth-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/light-it-labs/lightit-auth-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/light-it-labs/lightit-auth-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/light-it-labs/lightit-auth-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/light-it-labs/lightit-auth-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/light-it-labs/lightit-auth-laravel.svg?style=flat-square)](https://packagist.org/packages/light-it-labs/lightit-auth-laravel)

Lightit Auth Laravel simplifies authentication for Laravel applications by providing built-in support for JWT-based authentication and Google Single Sign-On (SSO). This package focuses on delivering secure and efficient authentication workflows while minimizing development time.  

## Key Features

- **JWT Authentication:**  
  - Secure login using email and password.  
  - Token refresh mechanism to extend user sessions seamlessly.  
  - Logout functionality with optional token blacklisting, configurable based on project needs.  

- **Google SSO Integration:**  
  - Enable Single Sign-On (SSO) via Google for a smoother user experience.  
  - Validate Google-issued tokens on the backend and issue your application's JWT tokens for secure session management.  

With these features, Lightit Auth Laravel is the perfect starting point for projects that require robust authentication solutions while maintaining flexibility and simplicity.  

## Installation

To get started, you need to manually add the repository URL to your `composer.json` file to download the package. Add the following configuration:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:Light-it-labs/lightit-auth-laravel.git"
        }
    ]
}

```
Once added, you can install the package via Composer using the following command:

```bash
 composer require light-it-labs/lightit-auth-laravel:@dev
```

After Composer has installed the Lightit Auth Laravel package, you should run the `auth:setup` Artisan command. This command will prompt you for your preferred authentication driver(s), whether Two-factor Authentication and/or a role/permission-based authorization will be used.

> **Note:** For existing projects, please refer to the section below to make necessary adjustments before running the `php artisan auth:setup` command.


## Installing Considerations

##### The following steps should be completed after running the `auth:setup` Artisan command.

### Installation in New Projects

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
### Installation in Projects with Existing Authentication (tymon/jwt-auth)

> **Note:** Complete the following steps before running the `php artisan auth:setup` command.

1. Remove the `config/jwt.php` file:
```bash
   rm config/jwt.php
```

2. Remove the `tymon/jwt-auth` package by running the following command:
```bash
composer remove tymon/jwt-auth
```
   
3. Replace all occurrences of the namespace `Tymon\JWTAuth` in your project with the namespace `PHPOpenSourceSaver\JWTAuth`.

## Autoload Configuration
To properly use this package, you need to configure the autoloading in your composer.json file. Add the following to the "autoload" section:

```json
{
    "autoload": {
        "psr-4": {
            "Lightit\\": "src/"
        }
    }
}
```
Once you’ve added this configuration, run the following command to regenerate the Composer autoloader:
```bash
 composer dump-autoload
```
This step ensures that all the namespaces used in the package, such as Lightit\Authentication\App\Controllers, are correctly recognized by your application.


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sergio Ojeda](https://github.com/sojeda)
- [Gianfranco Rocco](https://github.com/gianfranco-rocco)
- [Tomás Sueldo](https://github.com/tomisueldo)
- [Martín Silva](https://github.com/Tincho44)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
