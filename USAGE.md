[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

# bench Usage

`bench` is a modern PHP benchmarking package built for two workflows:

- regression tracking for your own package over time
- comparison suites across multiple implementations

It keeps the authoring model simple, but ships the operational pieces
that `phpbench` leaves awkward: snapshots, saved runs, side-by-side
comparisons, `ops/s`, richer percentiles, CI-friendly exit policies, and
attribute-based metadata instead of docblock annotations.

## Requirements

- PHP 8.5+

## Installation

```bash
composer require --dev cline/bench
```

## Quick Start

Initialize the package in a project:

```bash
vendor/bin/bench init
```

That scaffolds:

- `bench.php`
- `benchmarks/ExampleBench.php`

Create a benchmark class inside `benchmarks/`.

```php
<?php declare(strict_types=1);

namespace App\Benchmarks;

use Cline\Bench\Attributes\Assert;
use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Group;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Params;
use Cline\Bench\Attributes\Regression;
use Cline\Bench\Attributes\Revs;
use Cline\Bench\Attributes\Scenario;

#[Scenario('dto-transform')]
#[Competitor('bench')]
#[Group(['dto', 'comparison'])]
final class TransformBench
{
    #[Bench('transform-payload')]
    #[Iterations(5)]
    #[Revs(10)]
    #[Params([
        ['size' => 'small', 'count' => 100],
        ['size' => 'large', 'count' => 1_000],
    ])]
    #[Regression(metric: 'median', tolerance: '5%')]
    #[Assert('median', '<', 5_000_000.0)]
    public function benchTransformPayload(string $size, int $count): void
    {
        $items = range(1, $count);

        foreach ($items as $item) {
            strlen((string) $item.$size);
        }
    }
}
```

Run the suite:

```bash
vendor/bin/bench run
vendor/bin/bench run --save=baseline-local
```

## Authoring Benchmarks

### Class-Level Metadata

Use class attributes to define the comparison context for a suite:

- `#[Scenario('name')]`
- `#[Competitor('name')]`
- `#[Group(['a', 'b'])]`
- `#[Iterations(n)]`
- `#[Revs(n)]`
- `#[Warmup(n)]`
- `#[Before('method')]`
- `#[After('method')]`

### Method-Level Metadata

Use method attributes to define the measured benchmark subject:

- `#[Bench('name')]`
- `#[Params([...])]`
- `#[Assert(metric, operator, value)]`
- `#[Regression(metric: 'median', tolerance: '5%')]`

### Parameters

`#[Params]` expands one benchmark method into multiple benchmark cases.
Each element must map to the method parameters:

```php
#[Params([
    ['size' => 'small', 'count' => 100],
    ['size' => 'large', 'count' => 1_000],
])]
```

### Hooks

Use `#[Before]` and `#[After]` to run setup and teardown methods around
warmup and measured iterations.

### Assertions

Assertions let `run` fail directly in CI when a benchmark exceeds a
limit:

```php
#[Assert('median', '<', 5_000_000.0)]
```

### Regression Policies

Regression metadata applies when the current run is compared against a
snapshot:

```php
#[Regression(metric: 'median', tolerance: '5%')]
```

If no method-level regression attribute is present, `bench` falls back to
the global config default.

## Running Benchmarks

Basic usage:

```bash
vendor/bin/bench run
vendor/bin/bench run benchmarks
vendor/bin/bench run benchmarks/MyBench.php
```

Output formats:

```bash
vendor/bin/bench run --format=table
vendor/bin/bench run --format=md
vendor/bin/bench run --format=json
vendor/bin/bench run --format=csv
```

Selection flags:

```bash
vendor/bin/bench run --filter=transform
vendor/bin/bench run --group=comparison
vendor/bin/bench run --competitor=struct
vendor/bin/bench run --group=dto --competitor=struct --filter=profile
```

Live output:

```bash
vendor/bin/bench run --no-progress
```

Persistence:

```bash
vendor/bin/bench run --save=pr-123
```

`run` returns a non-zero exit code when any benchmark assertion fails.
When `--save` is provided, the run is stored under `.bench/runs/<name>.json`
and mirrored to `.bench/runs/latest.json`.

## Reporting

Render the current run without re-running benchmarks:

```bash
vendor/bin/bench report --format=table
vendor/bin/bench report --format=md
vendor/bin/bench report --format=json
vendor/bin/bench report --format=csv
```

Render the current run against a baseline:

```bash
vendor/bin/bench report --format=md --against=baseline
vendor/bin/bench report --format=json --against=run:latest
```

Rendered table and Markdown reports include environment and selection
context ahead of the result body. JSON reports include report metadata,
environment metadata, selections, metrics, comparisons, and policy
results.

## Snapshots

Save a named snapshot:

```bash
vendor/bin/bench snapshot:save baseline
vendor/bin/bench snapshot:save baseline --competitor=struct
```

Snapshots are stored in `.bench/snapshots` by default and include:

- raw samples
- derived statistics
- benchmark metadata
- environment fingerprint

Assert regressions:

```bash
vendor/bin/bench snapshot:assert --against=baseline
vendor/bin/bench snapshot:assert baseline --tolerance=3%
vendor/bin/bench snapshot:assert baseline --competitor=struct
```

Tolerance resolution order:

1. `#[Regression(...)]` on the benchmark method
2. configured default regression policy
3. CLI override when provided

## Comparisons

Compare the current run against a saved baseline or run:

```bash
vendor/bin/bench compare --against=baseline
vendor/bin/bench compare baseline
vendor/bin/bench compare snapshot:latest
vendor/bin/bench compare run:latest
vendor/bin/bench compare baseline --format=md
vendor/bin/bench compare baseline --format=csv
```

Comparison output includes:

- side-by-side medians
- winner
- ratio
- percent faster
- per-competitor `ops/s`
- significance label

### Compare Exit Policies

Use comparison policies to fail CI when the comparison quality changes in
meaningful ways:

```bash
vendor/bin/bench compare baseline --fail-on-winner-change
vendor/bin/bench compare baseline --min-ratio=2
vendor/bin/bench compare baseline --fail-on-winner-change --min-ratio=2
```

Supported policies:

- `--fail-on-winner-change`
- `--min-ratio=<float>`

## Configuration

Optional configuration lives in `bench.php` at the project root.

```php
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Environment\CompatibilityMode;

return BenchConfig::default()
    ->withBenchmarkPath('benchmarks')
    ->withSnapshotPath('.bench/snapshots')
    ->withRunPath('.bench/runs')
    ->withBootstrapPath('bench-bootstrap.php')
    ->withDefaultIterations(5)
    ->withDefaultRevolutions(1)
    ->withDefaultWarmupIterations(0)
    ->withCalibrationBudgetNanoseconds(5_000_000)
    ->withProcessIsolation(false)
    ->withDefaultRegression(metric: 'median', tolerance: '5%')
    ->withDefaultReportFormat('table')
    ->withProgressMetric('median')
    ->withProgressTimeUnit('μs')
    ->withPreferredCompetitors(['struct', 'base'])
    ->withCompetitorAliases([
        'struct' => 'Baloo',
        'spatie' => 'Spatie Data',
    ])
    ->withScenarioBaselines([
        'baloo-data' => 'snapshot:data-baseline',
        'baloo-profile' => 'snapshot:profile-baseline',
    ])
    ->withCompatibilityMode(CompatibilityMode::Warn);
```

### What Each Config Controls

- `withBenchmarkPath()` sets the discovery root used by `run`
- `withSnapshotPath()` changes where snapshots are stored
- `withRunPath()` changes where saved runs are stored
- `withBootstrapPath()` loads a bootstrap file before discovery/execution
- `withDefaultIterations()` sets fallback iterations
- `withDefaultRevolutions()` sets fallback revs
- `withDefaultWarmupIterations()` sets fallback warmup iterations
- `withCalibrationBudgetNanoseconds()` sets the minimum calibration budget
- `withProcessIsolation()` forces benchmark sampling through child processes
- `withDefaultRegression()` sets the suite-wide regression policy
- `withDefaultReportFormat()` changes the default render format
- `withProgressMetric()` selects the live run metric label, for example `median` or `average`
- `withProgressTimeUnit()` changes the live run time unit, for example `ns`, `μs`, `ms`, or `s`
- `withPreferredCompetitors()` orders comparison columns
- `withCompetitorAliases()` maps internal competitor ids to display labels
- `withScenarioBaselines()` pins specific scenarios to specific snapshots or runs
- `withCompatibilityMode()` controls environment mismatch behavior

### Preferred Competitors

`withPreferredCompetitors()` controls comparison ordering. Competitors
listed there appear first in the order provided. Any remaining
competitors are rendered alphabetically afterward.

### Competitor Aliases

`withCompetitorAliases()` lets you keep internal benchmark ids stable
while rendering human-friendly labels in table and Markdown output.

### Scenario Baselines

`withScenarioBaselines()` pins a scenario to a specific snapshot or run.
When configured, `compare`, `report`, and `snapshot:assert` can resolve
baselines without an explicit `--against` argument.

## Environment Compatibility

Compatibility modes:

- `CompatibilityMode::Ignore`
- `CompatibilityMode::Warn`
- `CompatibilityMode::Fail`

Environment fingerprints currently capture:

- PHP version
- PHP SAPI
- OS family
- architecture
- loaded extensions

## Execution Model

- warmup, revs, and iterations can be declared per benchmark or via config
- calibration can scale revs upward to hit a minimum measurement budget
- optional process isolation executes each sample in a fresh PHP child process
- saved snapshots and runs maintain a `latest` alias

## Reported Metrics

Each benchmark result includes:

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

## Current Scope

Implemented now:

- attribute-driven discovery
- grouped and parameterized benchmarks
- snapshots, saved runs, and `latest` aliases
- regression assertions
- comparison tables and compare exit policies
- calibrated revolutions and optional process isolation
- environment compatibility checks
- JSON, Markdown, table, and CSV reports
- selector flags for groups, competitors, and text filters
- `bench init` starter scaffolding

Not implemented yet:

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
