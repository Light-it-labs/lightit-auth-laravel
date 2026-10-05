<p align="center"><a href="https://lightit.io" target="_blank"><img src="https://lightit.io/images/Logo_purple.svg" width="400"></a></p>

# Laravel Auth Package

Laravel Auth Package adds optional auth features - 2FA, roles and permissions, OTP,
forgot password and social login - on top of the Light-it Laravel Boilerplate, which
owns the base authentication.
Supporting the following packages

[//]: # (- [Social login]&#40;https://github.com/firebase/php-jwt&#41;)

[//]: # (- [Google 2FA]&#40;https://github.com/antonioribeiro/google2fa-laravel&#41;)

[//]: # (- [Laravel Permission By Spatie]&#40;https://github.com/spatie/laravel-permission&#41;)

## Contents

- [Installation](#installation)
- [Social Login (Google)](docs/social-login.md)
- [Google 2FA](docs/google-2fa.md)
- [Passkeys](docs/passkeys.md)
- [Roles & Permissions](docs/permission.md)
- [OTP](docs/otp.md)
- [Forgot Password](docs/forgot-password.md)

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

Keep it a runtime dependency (never `composer require --dev`): if you select
Two-Factor Authentication, the package's own service provider registers the
`2fa` rate limiter on boot, and that provider needs to be loaded in
production for the limiter to exist.

Once installed, run the setup command:

```bash
php artisan auth:setup
```

If you are using Laravel Sail, you can run:

```bash
./vendor/bin/sail artisan auth:setup
```

This command walks you through the optional features it can add on top of the
boilerplate's own authentication. It no longer configures an authentication driver.

If the frontend layer can't find a React project next to your Laravel app (a
sibling directory named `frontend`, `front`, or `<app>-frontend`), pass its path
explicitly with `--frontend-path=<path>`. Relative paths resolve against the Laravel
application root, not your shell's current directory. The frontend layer is only
generated when Two-Factor Authentication, Social Login or Passkeys is selected, but an
invalid explicit path fails the whole command even if none of them is selected.

For each feature that needs manual steps, `auth:setup` prints them and leaves a short
checklist next to the code it wrote: `AUTH-<FEATURE>-TODO.md` in the backend root and
`AUTH-<FEATURE>-FRONTEND-TODO.md` in the frontend root. Tick the boxes, then delete the
file: nothing reads it. The feature's page under `docs/` explains every step in full.

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
