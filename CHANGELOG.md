# Changelog

All notable changes to this bundle are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). All Austral bundles share the same major and minor version.

## [Unreleased]

### Added

- `austral:make:entity` command, moved from `austral/tools-bundle`.

### Changed

- Generated entities use PHP attributes (Doctrine and Austral) instead of annotations.

### Fixed

- Templates read from this bundle (`src/Skeleton`) instead of `AustralToolsBundle`.
- Bundle services are loaded (`AustralMakerExtension`): the command was not registered.
- Generated `$id` and `EntityManager::$repository` compatible with the typed properties of the parent classes.
- Same class name everywhere for the master and its translation (`blog_post` → `BlogPost` / `BlogPostTranslate`).

### Removed

## [4.0.0] - YYYY-MM-DD

### Changed

- Symfony 7.4 support, PHP 8.2 minimum.
- Annotations replaced by PHP attributes.

[Unreleased]: https://github.com/austral-project/maker-bundle/compare/v4.0.0...4.x
[4.0.0]: https://github.com/austral-project/maker-bundle/releases/tag/v4.0.0
