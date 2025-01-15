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

To get started, install the package through Composer:

```bash
composer require light-it-labs/lightit-auth-laravel
```

After Composer has installed the Lightit Auth Laravel package, you should run the `auth:setup` Artisan command. This command will prompt you for your preferred authentication driver(s), whether Two-factor Authentication and/or a role/permission-based authorization will be used.

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="lightit-auth-laravel-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="lightit-auth-laravel-config"
```

This is the contents of the published config file:

```php
return [
];
```

## Installing Considerations
    
- ### Installation in New Projects
    You have to update your User Model and implements the JWTSubject Contract and implements two methods: ```getJWTIdentifier()``` and ```getJWTCustomClaims() ```
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
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
```
---
After this you have to create the routes for the methods developed on the package 
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lightit\Authentication\App\Controllers\LoginController;
use Lightit\Authentication\App\Controllers\LogoutController;
use Lightit\Authentication\App\Controllers\RefreshController;

Route::prefix('auth')->group(static function () {
    Route::post('login', LoginController::class);
    Route::post('loogut', LogoutController::class);
    Route::post('google', RefreshController::class);
});
```

---
Now you should:
 - Define ```AUTH_GUARD=api``` and ```AUTH_PASSWORD_BROKER=users``` on the .env file
 - Add the following code in the guard's array in the config/auth.php file 


```php
'api' => [
    'driver' => 'jwt',
    'provider' => 'users',
],
```
## Usage

```php
$lightit = new Lightit\Lightit();
echo $lightit->echoPhrase('Hello, Lightit!');
```

## Testing

```bash
composer test
```

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
