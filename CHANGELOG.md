# Changelog

All notable changes to this project are documented here. The project follows
[Semantic Versioning](https://semver.org/). During the `0.x` series, breaking
changes may occur between minor versions.

## [Unreleased]

## [0.2.0] - 2026-10-13

### Added

- Five route parameter rules: `RegexRule`, `UuidRule`, `SlugRule`, `EnumRule`, and `DateRule`.
- Support for date objects (`DateTimeInterface`) and PHP booleans when generating paths with their corresponding rules.
- Regression tests for typed parameter matching, URL generation, and compiled route manifests.

### Changed

- Validate provided route parameters when generating URLs; invalid values now raise `InvalidArgumentException`, including in optional path sections.
- `BoolRule` matches upper- and lowercase boolean keywords consistently.
- `IntRule` rejects values outside the native PHP integer range.
- `FloatRule` supports finite negative decimals and rejects non-finite values.
- Update the development branch alias to `0.2.x-dev`.

### Documentation

- Replace obsolete pre-publication Composer guidance with the published Packagist installation command.
- Expand the README into a topic-based guide covering routing, parameter rules, middleware, URL generation, compiled routes, error handling, observability, and related packages.
- Highlight the WilyCode PHP Ecosystem and document the additional rules with examples.
