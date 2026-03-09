[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

# bench

`bench` is a modern PHP benchmarking package built around attributes,
snapshots, saved runs, calibration, process isolation, and
comparison-first reporting.

## Requirements

- PHP 8.5+

## Installation

```bash
composer require --dev cline/bench
```

## Documentation

- [USAGE](USAGE.md) for detailed authoring, CLI, config, and regression workflows
- [CONTRIBUTING](CONTRIBUTING.md) for development workflow
- [CHANGELOG](CHANGELOG.md) for released changes

## Highlights

- attribute-first benchmark authoring
- built-in snapshots, saved runs, and `latest` aliases
- comparison tables with winners, reference gaps, reference gains, and `ops/s`
- regression assertions and comparison exit policies for CI
- parameterized benchmarks, groups, selectors, calibration, and process isolation

## Security

If you discover any security related issues, please use the
[GitHub security reporting form][link-security] rather than the issue
queue.

## License

The MIT License. Please see [License File](LICENSE.md) for more
information.

[ico-tests]: https://github.com/faustbrian/bench/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/bench.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/bench.svg

[link-tests]: https://github.com/faustbrian/bench/actions
[link-packagist]: https://packagist.org/packages/cline/bench
[link-downloads]: https://packagist.org/packages/cline/bench
[link-security]: https://github.com/faustbrian/bench/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
