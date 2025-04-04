# Draftsman for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/draftsmandev/draftsman.svg?style=flat-square)](https://packagist.org/packages/draftsmandev/draftsman)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/draftsmandev/draftsman/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/draftsmandev/draftsman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/draftsmandev/draftsman/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/draftsmandev/draftsman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/draftsmandev/draftsman.svg?style=flat-square)](https://packagist.org/packages/draftsmandev/draftsman)

A graphical tool that diagrams and edits Laravel eloquent models.

## Installation

You can install the package via composer:

```bash
composer require draftsmandev/draftsman
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="draftsman-config"
```

This is the contents of the published config file:

```php
return [
];
```

Maybe later, you can publish the views using

```bash
php artisan vendor:publish --tag="draftsman-views"
```

## Usage

```bash
php artisan draftsman:launch
```

## Testing

```bash
composer test
```

## Local Dev

Details coming

## Built With

* [Spatie's Package Skeleton](https://github.com/spatie/package-skeleton-laravel)
* [Spatie's Package Tools](https://github.com/spatie/laravel-package-tools)


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ron Northrip](https://github.com/ronnorthrip)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
