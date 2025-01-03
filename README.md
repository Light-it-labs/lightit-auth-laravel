# This is my package lightit-auth-laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/light-it-labs/lightit-auth-laravel.svg?style=flat-square)](https://packagist.org/packages/light-it-labs/lightit-auth-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/light-it-labs/lightit-auth-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/light-it-labs/lightit-auth-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/light-it-labs/lightit-auth-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/light-it-labs/lightit-auth-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/light-it-labs/lightit-auth-laravel.svg?style=flat-square)](https://packagist.org/packages/light-it-labs/lightit-auth-laravel)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
