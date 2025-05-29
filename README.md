<p align="center"><a href="https://lightit.io" target="_blank"><img src="https://lightit.io/images/Logo_purple.svg" width="400"></a></p>

# Laravel Auth Package

Laravel Auth Package simplifies authentication, authorization, roles and permissions setup for Laravel applications.
Supporting the following packages

[//]: # (- [PHP-Open-Source-Saver/jwt-auth]&#40;https://github.com/PHP-Open-Source-Saver/jwt-auth&#41;)

[//]: # (- [Laravel Sanctum &#40;Api Token Authentication&#41;]&#40;https://laravel.com/docs/12.x/sanctum&#41;)

[//]: # (- [Google SSO]&#40;https://github.com/googleapis/google-api-php-client&#41;)

[//]: # (- [Google 2FA]&#40;https://github.com/antonioribeiro/google2fa-laravel&#41;)

[//]: # (- [Laravel Permission By Spatie]&#40;https://github.com/spatie/laravel-permission&#41;)

## Contents

- [Installation](#installation)
- [JWT](docs/jwt.md)
- [Sanctum](docs/sanctum.md)
- [Google SSO](docs/google-sso.md)
- [Google 2FA](docs/google-2fa.md)
- [Roles & Permissions](docs/permission.md)

- [Credits](#credits)


## Installation


> [!IMPORTANT]
> This package is based on and tightly coupled with the Light-it Laravel Boilerplate.  
> It assumes conventions like:
> - Main namespace: `Lightit`
> - Specific file paths
> - Custom exceptions structure
>
> Keep this in mind if you plan to integrate it into a different project.

First, add the repository to your `composer.json`:
    
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Light-it-labs/lightit-auth-laravel.git"
        }
    ]
}
```
Then install the package via Composer:

```bash
composer require light-it-labs/lightit-auth-laravel
```

Once installed, run the setup command:

```bash
php artisan auth:setup
```

If you are using Laravel Sail, you can run:

```bash
./vendor/bin/sail artisan auth:setup
```

This command will guide you through the configuration of your authentication driver(s), Two-Factor Authentication (2FA), and/or Role and Permission system.

---

## Changelog

For recent changes, see the [CHANGELOG](CHANGELOG.md).

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sergio Ojeda](https://github.com/sojeda)
- [Gianfranco Rocco](https://github.com/gianfranco-rocco)
- [Tomás Sueldo](https://github.com/tomisueldo)
- [Martín Silva](https://github.com/Tincho44)
- [Ezequiel Flores](https://github.com/ezef)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
