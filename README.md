# Seamless Authentication Made Simple for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/light-it-labs/lightit-auth-laravel.svg?style=flat-square)](https://packagist.org/packages/light-it-labs/lightit-auth-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/light-it-labs/lightit-auth-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/light-it-labs/lightit-auth-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/light-it-labs/lightit-auth-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/light-it-labs/lightit-auth-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/light-it-labs/lightit-auth-laravel.svg?style=flat-square)](https://packagist.org/packages/light-it-labs/lightit-auth-laravel)  

This package is an all-in-one authentication package designed to enhance the flexibility and security of Laravel projects. This package offers a standardized approach to implementing essential and advanced authentication features, minimizing development time while ensuring best practices.  

With support for both traditional and modern authentication methods, the package simplifies tasks such as email-password login, Google Single Sign-On (SSO), token-based authentication, Two-Factor Authentication (2FA), and managing roles and permissions.  

## Key Features

- **JWT Authentication:** Login, logout (with blacklist support), and token refresh for secure and efficient user sessions.
- **Google SSO Integration:** Validate Google tokens from the frontend and issue custom JWTs from the backend.  
- **Laravel Sanctum Support:** API token-based authentication with logout functionality.  
- **Two-Factor Authentication:** Optional 2FA setup with QR code generation for apps like Google Authenticator.  
- **One-Time Passwords (OTP):** Add an extra layer of security with OTP-based authentication.  
- **Role and Permission Management:** Built-in structures and examples for implementing roles and permissions to standardize access control across projects.  

This package is built with flexibility in mind, allowing developers to enable or disable features based on the project’s requirements. Whether you’re starting a new application or enhancing an existing one, Lightit Auth Laravel provides a powerful foundation for managing user authentication & authorization.

## Installation

You can install the package via composer:

```bash
composer require light-it-labs/lightit-auth-laravel
```

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

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="lightit-auth-laravel-views"
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
- [Marín Silva](https://github.com/Tincho44)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
