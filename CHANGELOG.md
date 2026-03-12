# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added repository-level maintainer guidance in `AGENTS.md`.
- Initial release
- Enum-backed public configuration and benchmark metadata types
- Repository-local agent instructions in `AGENTS.md`

### Changed
- Assertion and regression metadata now use enum cases instead of raw strings
- `SignificanceCalculator` is now a `readonly` immutable value object
  with an explicit Psalm immutability annotation
- `Revs` has been renamed to `Revolutions` in the public benchmark API
- Parameterized benchmark identities now use canonical labels, with
  optional `_case` names for stable snapshots and named cases
- Compare policies now enforce the configured reference gap, and the
  public threshold flag is `--min-reference-gap`
- `USAGE.md` now documents the enum-based public API instead of the old
  string-based examples
- Comparison outputs and internal comparison types now consistently use
  reference-gap naming instead of the older ratio/speed-ratio terms
- Significance calculation is now configurable through `BenchConfig`,
  including alpha, minimum sample size, and disabling significance
- Remaining docs and tests now use `revolutions` and `reference gap`
  terminology consistently
- The packaged `bin/bench` entrypoint now resolves Composer autoloading
  correctly from installed `vendor/cline/bench` paths
- Process-isolated benchmarks now resolve Composer autoloading from the
  benchmark source path before falling back to the current working
  directory or package-local autoloaders
- JSON comparison output now exposes structured significance metadata,
  and the comparison domain uses typed significance results instead of
  magic strings
- The public benchmark threshold API now uses `Threshold` and
  `ThresholdOperator`, and serialized benchmark results expose
  `thresholds` instead of `assertions`
- `BenchConfig` now exposes grouped `storage()`, `execution()`,
  `reporting()`, and `comparison()` views for integrations
- `USAGE.md` now documents reference terminology and the versioned JSON
  schema contract, and the installed-package test suite now covers
  `vendor/bin/bench run`
- Scenario-specific configured baselines are now named scenario
  references, comparison/report metadata now uses `reference` fields,
  and the last legacy `assertions` snapshot fallback has been removed
- JSON schema documentation now lives in `docs/json-schema.md`, and
  report/compare command output configuration is now shared through a
  single console concern instead of being duplicated across commands
