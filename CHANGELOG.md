# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release
- Enum-backed public configuration and benchmark metadata types
- Repository-local agent instructions in `AGENTS.md`

### Changed
- Assertion and regression metadata now use enum cases instead of raw strings
- `Revs` has been renamed to `Revolutions` in the public benchmark API
- Parameterized benchmark identities now use canonical labels, with
  optional `_case` names for stable snapshots and named cases
- Compare policies now enforce the configured reference gap, and the
  public threshold flag is `--min-reference-gap`
- `USAGE.md` now documents the enum-based public API instead of the old
  string-based examples
- Comparison outputs and internal comparison types now consistently use
  reference-gap naming instead of the older ratio/speed-ratio terms
