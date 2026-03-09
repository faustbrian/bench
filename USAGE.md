[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

# bench Usage

`bench` is a PHP 8.5+ benchmarking package for two common workflows:

- benchmarking your own package over time and failing on regressions
- benchmarking multiple competitors side by side with comparison-first output

It is built around attributes, snapshots, saved runs, comparison reports,
environment fingerprints, and CI-friendly exit codes.

## Requirements

- PHP 8.5+

## Installation

```bash
composer require --dev cline/bench
```

## Quick Start

Scaffold the default files:

```bash
vendor/bin/bench init
```

That creates:

- `bench.php`
- `benchmarks/ExampleBench.php`

The generated config points `bench` at `benchmarks/` and loads
`vendor/autoload.php` as bootstrap.

Run your suite:

```bash
vendor/bin/bench run
```

Save a baseline snapshot:

```bash
vendor/bin/bench snapshot:save baseline
```

Compare a fresh run against that snapshot:

```bash
vendor/bin/bench compare baseline
```

Fail CI if regressions exceed tolerance:

```bash
vendor/bin/bench snapshot:assert baseline
```

## Concepts

### Scenario

A scenario groups comparable benchmarks together. In a DTO suite, a
scenario might be `dto-transform` or `dto-create`.

### Subject

A subject is the benchmark case inside a scenario, for example
`collection-transformation` or `object-creation`.

### Competitor

A competitor is the implementation being measured inside the same
scenario and subject, for example `struct`, `bag`, or `spatie`.

### Parameters

Parameters expand one benchmark method into multiple benchmark cases.
Each parameter set becomes its own measured result row.

### Snapshot

A snapshot is a persisted baseline stored under `.bench/snapshots`. It
contains raw samples, derived statistics, benchmark metadata, selection
metadata, and an environment fingerprint.

### Run

A saved run is a persisted ad hoc execution stored under `.bench/runs`.
Use saved runs when you want to inspect or compare a named run without
promoting it to a baseline snapshot.

## Benchmark Authoring

`bench` uses PHP attributes instead of docblock annotations.

### Supported Attributes

Class-level attributes:

- `#[Scenario('name')]`
- `#[Competitor('name')]`
- `#[Group(['a', 'b'])]`
- `#[Iterations(n)]`
- `#[Revolutions(n)]`
- `#[Warmup(n)]`
- `#[Before('method')]`
- `#[After('method')]`

Method-level attributes:

- `#[Bench('name')]`
- `#[Params([...])]`
- `#[Threshold(metric, operator, value)]`
- `#[Regression(metric: Metric::Median, tolerance: '5%')]`

### Example Benchmark

```php
<?php declare(strict_types=1);

namespace Benchmarks;

use Cline\Bench\Attributes\Threshold;
use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Before;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Group;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Params;
use Cline\Bench\Attributes\Regression;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Scenario;
use Cline\Bench\Attributes\Warmup;
use Cline\Bench\Enums\ThresholdOperator;
use Cline\Bench\Enums\Metric;

#[Scenario('dto-transform')]
#[Competitor('struct')]
#[Group(['dto', 'comparison'])]
#[Iterations(5)]
#[Revolutions(100)]
#[Warmup(1)]
#[Before('setUpPayload')]
final class TransformBench
{
    private array $payload = [];

    public function setUpPayload(): void
    {
        $this->payload = range(1, 1_000);
    }

    #[Bench('collection-transformation')]
    #[Params([
        ['size' => 'small'],
        ['size' => 'large'],
    ])]
    #[Threshold(Metric::Median, ThresholdOperator::LessThan, 5_000_000.0)]
    #[Regression(metric: Metric::Median, tolerance: '5%')]
    public function benchCollectionTransformation(string $size): void
    {
        foreach ($this->payload as $item) {
            strlen((string) $item.$size);
        }
    }
}
```

### Benchmark Naming Rules

- use one scenario for one comparison family
- use the same subject names across competitors
- keep competitor ids stable even if you later rename the rendered label
- use groups for coarse selection such as `dto`, `comparison`, or `profile`

### Parameters

`#[Params]` expands one method into multiple benchmark cases. Each entry
must map cleanly to the method signature:

```php
#[Params([
    ['size' => 'small', 'count' => 100],
    ['size' => 'large', 'count' => 1_000],
])]
```

### Hooks

`#[Before]` and `#[After]` run around warmup and measured iterations.
Use them for repeatable setup and teardown, not for one-time suite
initialization.

### Thresholds

Thresholds fail `bench run` when a benchmark exceeds a hard threshold.
This is useful for CI gates on your own package:

```php
#[Threshold(Metric::Median, ThresholdOperator::LessThan, 5_000_000.0)]
```

### Regression Policies

Regression metadata controls snapshot comparisons for one benchmark:

```php
#[Regression(metric: Metric::Median, tolerance: '5%')]
```

Resolution order for regression tolerance is:

1. CLI override such as `--tolerance=3%`
2. method-level `#[Regression(...)]`
3. global config default

## Commands

The application exposes six commands:

- `bench init`
- `bench run`
- `bench report`
- `bench compare`
- `bench snapshot:save`
- `bench snapshot:assert`

### `bench init`

Scaffolds a starter config and example benchmark.

```bash
vendor/bin/bench init
```

Behavior:

- creates `bench.php` if missing
- creates `benchmarks/ExampleBench.php` if missing
- skips existing files instead of overwriting them

### `bench run`

Runs benchmarks and renders results.

```bash
vendor/bin/bench run
vendor/bin/bench run benchmarks
vendor/bin/bench run benchmarks/MyBench.php
```

Flags:

- `--format=table|md|json|csv`
- `--save=<name>`
- `--filter=<text>`
- `--group=<group>` and repeated `--group=<group>`
- `--competitor=<id>` and repeated `--competitor=<id>`
- `--no-progress`
- `--no-significance`

Examples:

```bash
vendor/bin/bench run --format=table
vendor/bin/bench run --format=md
vendor/bin/bench run --format=json
vendor/bin/bench run --format=csv
vendor/bin/bench run --group=dto --competitor=struct
vendor/bin/bench run --filter=profile --no-progress
vendor/bin/bench run --save=pr-123
```

Behavior:

- discovers benchmarks from the provided path or configured benchmark path
- shows live progress in `table` mode unless `--no-progress` is set
- disables significance labels in rendered output when `--no-significance`
  is set
- returns a non-zero exit code when benchmark thresholds fail
- saves runs to `.bench/runs/<name>.json` and mirrors them to `latest.json`

### `bench report`

Runs benchmarks and renders a report, optionally against a saved
reference.

```bash
vendor/bin/bench report --format=table
vendor/bin/bench report --format=md --against=baseline
vendor/bin/bench report --format=json --against=run:latest
```

Flags:

- `--format=table|md|json|csv`
- `--against=<snapshot-or-run>`
- `--filter=<text>`
- `--group=<group>` and repeated `--group=<group>`
- `--competitor=<id>` and repeated `--competitor=<id>`
- `--no-significance`

Behavior:

- without `--against`, renders the current run only
- with `--against`, renders a comparison report
- if `--against` is omitted and `bench.php` defines scenario baselines,
  `report` resolves those baselines automatically

### `bench compare`

Runs benchmarks and compares the current run against a saved reference.

```bash
vendor/bin/bench compare baseline
vendor/bin/bench compare --against=baseline
vendor/bin/bench compare snapshot:latest
vendor/bin/bench compare run:latest
```

Flags:

- `--against=<snapshot-or-run>`
- `--format=table|md|json|csv`
- `--filter=<text>`
- `--group=<group>` and repeated `--group=<group>`
- `--competitor=<id>` and repeated `--competitor=<id>`
- `--fail-on-winner-change`
- `--min-reference-gap=<float>`
- `--no-significance`

Examples:

```bash
vendor/bin/bench compare baseline --format=md
vendor/bin/bench compare baseline --fail-on-winner-change
vendor/bin/bench compare baseline --min-reference-gap=2
vendor/bin/bench compare baseline --fail-on-winner-change --min-reference-gap=2
```

Behavior:

- accepts a positional reference name or `--against`
- resolves snapshots with `snapshot:<name>` and runs with `run:<name>`
- if no explicit reference is provided, scenario baselines from
  `bench.php` may satisfy the comparison
- returns a non-zero exit code when compare policies fail

### `bench snapshot:save`

Runs benchmarks and stores a named snapshot.

```bash
vendor/bin/bench snapshot:save baseline
vendor/bin/bench snapshot:save baseline benchmarks
vendor/bin/bench snapshot:save baseline --competitor=struct
```

Flags:

- `--filter=<text>`
- `--group=<group>` and repeated `--group=<group>`
- `--competitor=<id>` and repeated `--competitor=<id>`

Behavior:

- writes `.bench/snapshots/<name>.json`
- updates `.bench/snapshots/latest.json`
- captures environment fingerprint, selection, settings, raw samples, and
  derived statistics

### `bench snapshot:assert`

Runs benchmarks and fails when regressions exceed allowed tolerance.

```bash
vendor/bin/bench snapshot:assert baseline
vendor/bin/bench snapshot:assert --against=baseline
vendor/bin/bench snapshot:assert baseline --tolerance=3%
```

Flags:

- `--against=<snapshot>`
- `--tolerance=<percent>`
- `--filter=<text>`
- `--group=<group>` and repeated `--group=<group>`
- `--competitor=<id>` and repeated `--competitor=<id>`

Behavior:

- accepts a positional baseline name or `--against`
- can resolve scenario baselines from config when no explicit baseline is
  provided
- prints one regression decision per benchmark
- returns a non-zero exit code when any benchmark exceeds tolerance

## References

`bench` uses `reference` as the generic term for any saved comparison
target. A reference can be either a snapshot or a saved run.

Anywhere a reference is accepted, you can use:

- `baseline`
- `snapshot:baseline`
- `snapshot:latest`
- `run:latest`
- `run:pr-123`

If no prefix is given, `bench` resolves the name as a snapshot first.

Scenario baselines are just reference mappings stored in `bench.php`.

## Selection Flags

The same selection model is shared across `run`, `report`, `compare`,
`snapshot:save`, and `snapshot:assert`.

- `--filter=<text>` matches scenario, subject, competitor, and groups
- `--group=<name>` narrows to one or more benchmark groups
- `--competitor=<id>` narrows to one or more competitors

Examples:

```bash
vendor/bin/bench run --filter=transform
vendor/bin/bench run --group=comparison
vendor/bin/bench run --competitor=struct
vendor/bin/bench compare baseline --group=dto --competitor=spatie
```

## Output Formats

### Table

Table output is the default and is optimized for terminal use.

When a suite contains multiple competitors under the same scenario,
`bench` renders comparison-first tables with:

- side-by-side durations per competitor
- winner
- closest reference gap or slowest reference gap, depending on config
- closest reference gain percentage or slowest reference gain percentage
- per-competitor `ops/s`
- overall wins and geometric mean reference gap

### Markdown

Markdown output is suitable for pull requests, comments, and artifacts.
It includes the same comparison information as the table format, plus
environment and selection context.

### JSON

JSON output is intended for tooling and automation. It includes:

- report metadata
- environment metadata
- execution settings
- benchmark selection metadata
- raw samples
- derived statistics
- comparison rows
- compare policy results when relevant

JSON is a versioned public contract. Consumers should branch on
`schema_version` and treat unknown fields as additive.

Current guarantees for `schema_version: 1`:

- `report_type` distinguishes `run`, `comparison`, and `snapshot`
- `generated_at` is an ISO-8601 UTC timestamp
- `environment` and `settings` are emitted when that context exists
- current-run rows include `summary`, `samples`, `parameters`,
  `parameter_label`, `groups`, `thresholds`, and `regression`
- comparison rows include `winner`, `reference_gap`, `reference_gain`,
  and structured `significance`
- structured significance includes:
  - `status`
  - `label`
  - `p_value`
  - `alpha`
  - `minimum_samples`

### CSV

CSV output is intended for spreadsheet import or downstream aggregation.

## Reading Comparison Output

For multi-competitor suites, the comparison summary focuses on the
fastest competitor in each row and a configured reference competitor.

Key fields:

- `Winner`: fastest competitor for that benchmark row
- `Closest Reference Gap`: fastest competitor versus the next-fastest competitor
- `Closest Reference Gain`: percentage lead of the fastest competitor over the
  next-fastest competitor
- `Slowest Reference Gap`: fastest competitor versus the slowest competitor
- `Slowest Reference Gain`: percentage lead of the fastest competitor over the
  slowest competitor
- per-competitor `Ops/s`: throughput for each displayed competitor

If `withComparisonReference(ComparisonReference::Slowest)` is configured, the summary gap
and gain columns are computed against the slowest competitor instead of
the closest competitor. Compare policies use that same configured
reference, so `--min-reference-gap` always enforces the same gap that
the summary columns describe.

## `bench.php` Configuration

Configuration is optional and lives at the project root as `bench.php`.

```php
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ReportFormat;
use Cline\Bench\Enums\TimeUnit;
use Cline\Bench\Environment\CompatibilityMode;

return BenchConfig::default()
    ->withBenchmarkPath('benchmarks')
    ->withSnapshotPath('.bench/snapshots')
    ->withRunPath('.bench/runs')
    ->withBootstrapPath('vendor/autoload.php')
    ->withDefaultIterations(5)
    ->withDefaultRevolutions(1)
    ->withDefaultWarmupIterations(0)
    ->withCalibrationBudgetNanoseconds(0)
    ->withProcessIsolation(false)
    ->withDefaultRegression(metric: Metric::Median, tolerance: '5%')
    ->withDefaultReportFormat(ReportFormat::Table)
    ->withProgressMetric(Metric::Median)
    ->withProgressTimeUnit(TimeUnit::Microseconds)
    ->withComparisonReference(ComparisonReference::Closest)
    ->withPreferredCompetitors(['struct', 'base'])
    ->withCompetitorAliases([
        'struct' => 'Baloo',
        'spatie' => 'Spatie Data',
    ])
    ->withScenarioBaselines([
        'baloo-data' => 'snapshot:data-baseline',
        'baloo-profile' => 'snapshot:profile-baseline',
    ])
    ->withNumberSeparators(decimalSeparator: '.', thousandsSeparator: ',')
    ->withRawNumberDecimals(3)
    ->withDurationDecimals(3)
    ->withOperationsDecimals(0)
    ->withProgressDecimals(timeDecimals: 3, operationsDecimals: 3)
    ->withRatioDecimals(2)
    ->withPercentageDecimals(1, 2)
    ->withCompatibilityMode(CompatibilityMode::Warn);
```

### Defaults

Current defaults:

- benchmark path: `benchmarks`
- snapshot path: `.bench/snapshots`
- run path: `.bench/runs`
- preferred competitors: `['struct', 'base']`
- bootstrap path: `null`
- default iterations: `5`
- default revolutions: `1`
- default warmup iterations: `0`
- calibration budget: `0`
- process isolation: `false`
- default regression: `median`, `5%`
- default report format: `table`
- progress metric: `median`
- progress time unit: `μs`
- comparison reference: `closest`
- decimal separator: `.`
- thousands separator: `,`
- raw number decimals: `3`
- duration decimals: `3`
- operations decimals: `0`
- progress time decimals: `3`
- progress operations decimals: `3`
- ratio decimals: `2`
- percentage decimals: `1`
- delta percentage decimals: `2`
- significance enabled: `true`
- significance alpha: `0.05`
- significance minimum samples: `2`
- compatibility mode: `warn`

### Grouped Config Views

`BenchConfig` is still the fluent builder you return from `bench.php`,
but it also exposes grouped value objects for integrations and internal
code:

- `storage()`
- `execution()`
- `reporting()`
- `comparison()`

These views mirror the flat config values without changing the public
builder API.

### Path and Bootstrap Controls

- `withBenchmarkPath()` sets the default discovery path
- `withSnapshotPath()` changes where snapshots are stored
- `withRunPath()` changes where saved runs are stored
- `withBootstrapPath()` requires a file before discovery and execution

Typical bootstrap use:

```php
return BenchConfig::default()
    ->withBootstrapPath('vendor/autoload.php');
```

### Execution Controls

- `withDefaultIterations()` sets fallback iterations
- `withDefaultRevolutions()` sets fallback revolutions
- `withDefaultWarmupIterations()` sets fallback warmup iterations
- `withCalibrationBudgetNanoseconds()` raises revolutions until a minimum
  measurement budget is reached
- `withProcessIsolation()` runs each measured sample in a child process

Example:

```php
return BenchConfig::default()
    ->withDefaultIterations(10)
    ->withDefaultRevolutions(100)
    ->withDefaultWarmupIterations(2)
    ->withCalibrationBudgetNanoseconds(5_000_000)
    ->withProcessIsolation(true);
```

### Regression Controls

- `withDefaultRegression(metric: Metric::Median, tolerance: '5%')`

Use this when you want a project-wide fallback for snapshot thresholds.

### Significance Controls

- `withSignificance(alpha: 0.05, minimumSamples: 2)`
- `withoutSignificance()`

Use these when you want to tighten or disable significance reporting in
comparison outputs.

### Output and Formatting Controls

- `withDefaultReportFormat(ReportFormat::...)`
- `withProgressMetric(Metric::Median|Metric::Mean)`
- `withProgressTimeUnit(TimeUnit::Nanoseconds|Microseconds|Milliseconds|Seconds)`
- `withComparisonReference(ComparisonReference::Closest|Slowest)`

`withComparisonReference()` controls what the summary gap columns mean:

- `closest`: compare the winner against the next-fastest competitor
- `slowest`: compare the winner against the slowest competitor

### Competitor Ordering and Labels

- `withPreferredCompetitors()` brings listed competitors to the front in
  the exact order provided
- any remaining competitors are ordered alphabetically
- `withCompetitorAliases()` maps internal ids to rendered labels

Example:

```php
return BenchConfig::default()
    ->withPreferredCompetitors(['struct', 'bag', 'spatie'])
    ->withCompetitorAliases([
        'struct' => 'Baloo',
        'spatie' => 'Spatie Data',
    ]);
```

### Scenario Baselines

`withScenarioBaselines()` maps scenario ids to snapshot or run
references:

```php
return BenchConfig::default()
    ->withScenarioBaselines([
        'dto-transform' => 'snapshot:transform-baseline',
        'dto-create' => 'run:pr-123',
    ]);
```

This allows `report`, `compare`, and `snapshot:assert` to resolve
baselines without an explicit `--against`.

### Number Formatting

Use these methods to control separators and precision:

- `withNumberSeparators(decimalSeparator: '.', thousandsSeparator: ',')`
- `withRawNumberDecimals(int)`
- `withDurationDecimals(int)`
- `withOperationsDecimals(int)`
- `withProgressDecimals(timeDecimals: int, operationsDecimals: ?int = null)`
- `withRatioDecimals(int)`
- `withPercentageDecimals(int, ?int = null)`

Example for German-style formatting:

```php
return BenchConfig::default()
    ->withNumberSeparators(decimalSeparator: ',', thousandsSeparator: '.')
    ->withDurationDecimals(0)
    ->withOperationsDecimals(0)
    ->withProgressDecimals(timeDecimals: 0, operationsDecimals: 0)
    ->withRatioDecimals(2)
    ->withPercentageDecimals(2);
```

### Environment Compatibility

`bench` records environment fingerprints in snapshots and saved runs.

Supported compatibility modes:

- `CompatibilityMode::Ignore`
- `CompatibilityMode::Warn`
- `CompatibilityMode::Fail`

Environment fingerprints currently include:

- PHP version
- PHP SAPI
- OS family
- architecture
- loaded extensions

If a baseline environment differs from the current one:

- `Ignore`: continue silently
- `Warn`: print the mismatch and continue
- `Fail`: print the mismatch and exit non-zero

## Storage Layout

Default storage paths:

- snapshots: `.bench/snapshots`
- runs: `.bench/runs`

Both snapshots and runs keep a `latest.json` alias in addition to named
files.

## Execution Model

The runtime model is:

- discover benchmark classes from the configured path
- expand parameter sets into concrete benchmark cases
- apply class and method metadata
- optionally warm up the case
- optionally calibrate revolutions upward to hit the configured budget
- run each measured iteration
- optionally isolate each sample in a fresh child process
- compute derived statistics from raw samples

`bench` creates a fresh benchmark instance for each warmup and measured
iteration.

## Reported Metrics

Each benchmark result records:

- sample count
- min
- max
- mean
- median
- standard deviation
- relative margin of error
- `p75`
- `p95`
- `p99`
- `ops/s`

## CI Patterns

Save a baseline locally or on a scheduled job:

```bash
vendor/bin/bench snapshot:save baseline
```

Fail CI when regressions exceed tolerance:

```bash
vendor/bin/bench snapshot:assert baseline --tolerance=5%
```

Fail CI when comparison quality changes:

```bash
vendor/bin/bench compare baseline --fail-on-winner-change --min-reference-gap=2
```

Render a PR-friendly comparison artifact:

```bash
vendor/bin/bench report --format=md --against=baseline
```

## Scope

Implemented now:

- attribute-driven discovery
- grouped and parameterized benchmarks
- snapshots, saved runs, and `latest` aliases
- regression thresholds
- comparison-first terminal and Markdown reports
- compare exit policies
- calibrated revolutions and optional process isolation
- environment compatibility checks
- JSON, Markdown, table, and CSV reports
- selector flags for groups, competitors, and text filters
- `bench init` starter scaffolding
- configurable comparison ordering, aliases, and number formatting

Not implemented:

- profiler or flamegraph integration
- remote result storage

## Related Files

- [README](README.md)
- [CONTRIBUTING](CONTRIBUTING.md)
- [CHANGELOG](CHANGELOG.md)
- [LICENSE](LICENSE.md)

[ico-tests]: https://github.com/faustbrian/bench/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/bench.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/bench.svg

[link-tests]: https://github.com/faustbrian/bench/actions
[link-packagist]: https://packagist.org/packages/cline/bench
[link-downloads]: https://packagist.org/packages/cline/bench
