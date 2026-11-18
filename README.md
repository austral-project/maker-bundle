# Austral Maker Bundle

[![Version](https://img.shields.io/packagist/v/austral/maker-bundle?label=version)](https://packagist.org/packages/austral/maker-bundle)
[![CI](https://github.com/austral-project/maker-bundle/actions/workflows/ci.yml/badge.svg?branch=4.x)](https://github.com/austral-project/maker-bundle/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/austral/maker-bundle/php)](https://packagist.org/packages/austral/maker-bundle)
[![Symfony](https://img.shields.io/badge/symfony-7.4-black)](https://symfony.com/releases/7.4)
[![License](https://img.shields.io/packagist/l/austral/maker-bundle)](LICENSE)

Code generators for Austral: entities, admin modules and project skeleton files. Development only.

> **Work in progress.** This bundle is being built for Austral 4.0 and has not been released yet.

> This bundle is part of [Austral](https://github.com/austral-project), a modular back office and CMS for Symfony.

## What it will provide

- **Entity generator**: creates an Austral entity with the traits it needs (translations, files, SEO, domains…).
- **Admin module generator**: creates the admin configuration for an entity.
- **Skeleton files**: generates the starting files of an Austral project.

These generators come from `tools-bundle` and `admin-bundle` in Austral 3.1. Moving them here keeps `tools-bundle` free of any dependency on the other Austral bundles.

## Requirements

- PHP 8.2 or higher
- Symfony 7.4

## Installation

Install it as a development dependency only:

```bash
composer require --dev austral/maker-bundle
```

## Documentation

The full documentation will be available at https://austral.dev.

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/austral-project/.github/blob/master/CONTRIBUTING.md). To report a security issue, follow the [security policy](https://github.com/austral-project/.github/blob/master/SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).

## Credits

Created by [Matthieu Beurel](https://www.mbeurel.com). Sponsored by [Yipikai Studio](https://yipikai.studio).
