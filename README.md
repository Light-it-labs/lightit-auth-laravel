<p align="center"><a href="https://lightit.io" target="_blank"><img src="https://lightit.io/images/Logo_purple.svg" width="400"></a></p>

# Laravel Auth Package

Laravel Auth Package simplifies authentication, authorization, roles and permissions setup for Laravel applications.
Supporting the following packages

[//]: # (- [PHP-Open-Source-Saver/jwt-auth]&#40;https://github.com/PHP-Open-Source-Saver/jwt-auth&#41;)

[//]: # (- [Laravel Sanctum &#40;Api Token Authentication&#41;]&#40;https://laravel.com/docs/12.x/sanctum&#41;)

[//]: # (- [Google SSO]&#40;https://github.com/googleapis/google-api-php-client&#41;)

[//]: # (- [Google 2FA]&#40;https://github.com/antonioribeiro/google2fa-laravel&#41;)

[//]: # (- [Laravel Permission By Spatie]&#40;https://github.com/spatie/laravel-permission&#41;)

# Contents

- [Installation](#installation)
- [JWT](docs/jwt.md)
- [Sanctum](docs/sanctum.md)
- [Google SSO](docs/google-sso.md)
- [Google 2FA](docs/google-2fa.md)
- [Roles & Permissions](docs/permission.md)

- [Credits](#credits)


## Installation


> [!IMPORTANT]
> This package is based on and highly coupled to the Laravel Boilerplate from Light-It. For example, the main namespace is assumed to be 'Lightit', the paths where files are created, the exceptions those files use, and so on.
>
> Just keep this in mind if you plan to use it in a different project.


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
Once added, you can install the package via Composer using the following command:

```bash
 composer require light-it-labs/lightit-auth-laravel:@dev
```

After Composer has installed the Lightit Auth Laravel package, you should run the `auth:setup` Artisan command. This command will prompt you for your preferred authentication driver(s), whether Two-factor Authentication and/or a role/permission-based authorization will be used.

> **Note:** For existing projects, please refer to the section below to make necessary adjustments before running the `php artisan auth:setup` command.

---

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
