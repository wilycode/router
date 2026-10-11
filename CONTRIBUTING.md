# Contributing to WilyCode Router

Thank you for your interest in contributing.

## Before opening a pull request

1. Open an issue for significant API changes so the design can be discussed.
2. Fork the repository, create a branch, and keep each pull request focused.
3. Use PHP 8.4 or newer; keep strict types and 4-space indentation.
4. Run `composer install`, `composer validate --strict`, and `composer test`.
5. Add or update tests and README examples when behavior changes.

Please keep Router independent from optional integrations:

- `wilycode/route-attribute` provides attribute-based declarations outside core.
- `wilycode/router-profiler` implements optional diagnostics outside core.

Do not add either package as a required dependency of `wilycode/router`.

## Reporting problems

Use [GitHub Issues](https://github.com/wilycode/router/issues) for bug reports and
feature proposals. Include the PHP version, steps to reproduce, and a minimal
reproducer without secrets or private configuration.

## License

By contributing code, you agree that your contribution will be distributed under
the project's [MIT license](LICENSE).
