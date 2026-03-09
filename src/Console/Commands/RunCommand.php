<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Console\Commands;

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Configuration\BenchConfigLoader;
use Cline\Bench\Console\Concerns\FormatsResults;
use Cline\Bench\Enums\ComparisonReference;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\TimeUnit;
use Cline\Bench\Environment\EnvironmentFingerprint;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Execution\BenchmarkRunner;
use Cline\Bench\Execution\BenchmarkSelection;
use Cline\Bench\Snapshot\SnapshotStore;
use Cline\Bench\Storage\RunStore;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use const DATE_ATOM;
use const STR_PAD_LEFT;

use function array_filter;
use function array_values;
use function count;
use function crc32;
use function explode;
use function getcwd;
use function gmdate;
use function implode;
use function is_array;
use function is_string;
use function max;
use function mb_rtrim;
use function mb_str_pad;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function number_format;
use function preg_match;
use function realpath;
use function sprintf;
use function str_repeat;
use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[AsCommand(name: 'run', description: 'Run benchmarks and report results.')]
final class RunCommand extends Command
{
    use FormatsResults;

    /** @var list<string> */
    private array $preferredCompetitors = ['struct', 'base'];

    /** @var array<string, string> */
    private array $competitorAliases = [];

    private Metric $progressMetric = Metric::Median;

    private TimeUnit $progressTimeUnit = TimeUnit::Microseconds;

    private ComparisonReference $comparisonReference = ComparisonReference::Closest;

    private string $decimalSeparator = '.';

    private string $thousandsSeparator = ',';

    private int $rawNumberDecimals = 3;

    private int $durationDecimals = 3;

    private int $operationsDecimals = 0;

    private int $progressTimeDecimals = 3;

    private int $progressOperationsDecimals = 3;

    private int $ratioDecimals = 2;

    private int $percentageDecimals = 1;

    private int $deltaPercentageDecimals = 2;

    #[Override()]
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Benchmark path');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json, md, csv)');
        $this->addOption('save', null, InputOption::VALUE_REQUIRED, 'Persist the run under a name');
        $this->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Only run benchmarks matching the given text');
        $this->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks in the given group');
        $this->addOption('competitor', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks for the given competitor');
        $this->addOption('no-progress', null, InputOption::VALUE_NONE, 'Suppress live benchmark progress output');
    }

    #[Override()]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = BenchConfigLoader::load();
        $this->preferredCompetitors = $config->preferredCompetitors;
        $this->competitorAliases = $config->competitorAliases;
        $this->progressMetric = $config->progressMetric;
        $this->progressTimeUnit = $config->progressTimeUnit;
        $this->comparisonReference = $config->comparisonReference;
        $this->decimalSeparator = $config->decimalSeparator;
        $this->thousandsSeparator = $config->thousandsSeparator;
        $this->rawNumberDecimals = $config->rawNumberDecimals;
        $this->durationDecimals = $config->durationDecimals;
        $this->operationsDecimals = $config->operationsDecimals;
        $this->progressTimeDecimals = $config->progressTimeDecimals;
        $this->progressOperationsDecimals = $config->progressOperationsDecimals;
        $this->ratioDecimals = $config->ratioDecimals;
        $this->percentageDecimals = $config->percentageDecimals;
        $this->deltaPercentageDecimals = $config->deltaPercentageDecimals;
        $this->bootstrap($config);
        $format = $this->nullableOptionString($input, 'format') ?? $config->defaultReportFormat->value;
        $selection = $this->selection($input);
        $metadata = $this->reportMetadata('run', $config, $selection);
        $showProgress = $format === 'table'
            && !$this->flag($input, 'no-progress')
            && !$output->isQuiet();

        if ($showProgress) {
            $environment = is_array($metadata['environment'] ?? null) ? $metadata['environment'] : [];
            $settings = is_array($metadata['settings'] ?? null) ? $metadata['settings'] : [];
            $environmentRows = [
                'PHP' => $this->stringValue($environment['php_version'] ?? null),
                'SAPI' => $this->stringValue($environment['php_sapi'] ?? null),
                'Platform' => sprintf(
                    '%s %s',
                    $this->stringValue($environment['os_family'] ?? null),
                    $this->stringValue($environment['architecture'] ?? null),
                ),
                'Process Isolation' => (($settings['process_isolation'] ?? false) === true) ? 'enabled' : 'disabled',
            ];

            $environmentWidth = $this->plainSectionWidth($environmentRows);

            $output->writeln($this->coloredSectionTitle('Environment', 'cyan'));
            $output->writeln($this->renderPlainDetailSectionBody($environmentRows, $environmentWidth));
            $output->writeln('');
            $output->writeln($this->coloredSectionTitle('Running Benchmarks', 'cyan'));
        }

        $results = new BenchmarkRunner()->runPath(
            $this->benchmarkPath($input, $config),
            $config,
            $showProgress
                ? function (
                    string $phase,
                    int $position,
                    int $total,
                    string $scenario,
                    string $subject,
                    string $competitor,
                    ?BenchmarkResult $result,
                ) use ($output): void {
                    if ($phase === 'running' || !$result instanceof BenchmarkResult) {
                        return;
                    }

                    $output->writeln($this->termwindProgressLine(
                        $position,
                        $total,
                        $scenario,
                        $subject,
                        $competitor,
                        $result,
                    ));
                }
            : null,
            $selection,
        );
        $saveName = $this->nullableOptionString($input, 'save');

        if ($saveName !== null) {
            new RunStore(
                new SnapshotStore($this->resolvePath($config->runPath)),
            )->save(
                $saveName,
                $results,
                $metadata,
            );
        }

        $renderMetadata = $showProgress ? $this->withoutEnvironmentMetadata($metadata) : $metadata;

        $rendered = match ($format) {
            'json' => $this->asJson($results, $renderMetadata),
            'csv' => $this->asCsv($results),
            'md' => $this->asMarkdown($results, $renderMetadata),
            default => $this->asTable($results, $renderMetadata),
        };

        if ($format === 'table') {
            $output->writeln('');
            $output->writeln($this->coloredSectionTitle('Results', 'green'));
            $output->writeln('');
            $rendered = $this->colorizeRenderedSections($rendered);
        }

        $output->writeln($rendered);

        if (!$this->hasFailedAssertions($results)) {
            return self::SUCCESS;
        }

        $output->writeln('Benchmark assertions failed.');

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    protected function preferredCompetitors(): array
    {
        return $this->preferredCompetitors;
    }

    /**
     * @return array<string, string>
     */
    protected function competitorAliases(): array
    {
        return $this->competitorAliases;
    }

    protected function comparisonReference(): ComparisonReference
    {
        return $this->comparisonReference;
    }

    protected function decimalSeparator(): string
    {
        return $this->decimalSeparator;
    }

    protected function thousandsSeparator(): string
    {
        return $this->thousandsSeparator;
    }

    protected function rawNumberDecimals(): int
    {
        return $this->rawNumberDecimals;
    }

    protected function durationDecimals(): int
    {
        return $this->durationDecimals;
    }

    protected function operationsDecimals(): int
    {
        return $this->operationsDecimals;
    }

    protected function ratioDecimals(): int
    {
        return $this->ratioDecimals;
    }

    protected function percentageDecimals(): int
    {
        return $this->percentageDecimals;
    }

    protected function deltaPercentageDecimals(): int
    {
        return $this->deltaPercentageDecimals;
    }

    private function benchmarkPath(InputInterface $input, BenchConfig $config): string
    {
        $value = $input->getArgument('path');

        if ($value === null) {
            return $this->resolvePath($config->benchmarkPath);
        }

        throw_unless(is_string($value), InvalidArgumentException::class, 'Argument [path] must be a string.');

        /** @var string $value */
        return $this->resolvePath($value);
    }

    private function nullableOptionString(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Option [%s] must be a string.', $name));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function optionStrings(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private function flag(InputInterface $input, string $name): bool
    {
        return (bool) $input->getOption($name);
    }

    private function selection(InputInterface $input): BenchmarkSelection
    {
        return new BenchmarkSelection(
            filter: $this->nullableOptionString($input, 'filter'),
            groups: $this->optionStrings($input, 'group'),
            competitors: $this->optionStrings($input, 'competitor'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportMetadata(string $reportType, BenchConfig $config, BenchmarkSelection $selection): array
    {
        return [
            'schema_version' => 1,
            'report_type' => $reportType,
            'generated_at' => gmdate(DATE_ATOM),
            'environment' => EnvironmentFingerprint::capture()->toArray(),
            'selection' => $selection->toArray(),
            'settings' => [
                'process_isolation' => $config->processIsolation,
                'default_iterations' => $config->defaultIterations,
                'default_revolutions' => $config->defaultRevolutions,
                'default_warmup_iterations' => $config->defaultWarmupIterations,
            ],
        ];
    }

    private function bootstrap(BenchConfig $config): void
    {
        if ($config->bootstrapPath === null) {
            return;
        }

        require_once $this->resolvePath($config->bootstrapPath);
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        if ($path[0] === '/') {
            return $path;
        }

        $workingDirectory = getcwd();

        if (!is_string($workingDirectory)) {
            return $path;
        }

        $resolvedPath = realpath(mb_rtrim($workingDirectory, '/').'/'.$path);

        return $resolvedPath === false ? mb_rtrim($workingDirectory, '/').'/'.$path : $resolvedPath;
    }

    /**
     * @param  array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function withoutEnvironmentMetadata(array $metadata): array
    {
        unset($metadata['environment'], $metadata['settings']);

        return $metadata;
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    private function hasFailedAssertions(array $results): bool
    {
        foreach ($results as $result) {
            foreach ($result->assertions as $assertion) {
                if (!$assertion->passed) {
                    return true;
                }
            }
        }

        return false;
    }

    private function termwindProgressLine(int $position, int $total, string $scenario, string $subject, string $competitor, BenchmarkResult $result): string
    {
        $progress = mb_str_pad(
            sprintf('%d/%d', $position, $total),
            mb_strlen(sprintf('%d/%d', $total, $total)),
            ' ',
            STR_PAD_LEFT,
        );
        $metric = $this->progressMetricLabel().' '.$this->formatConfiguredTimeMetric($result);
        $operations = $this->formatOperationsMetric($result);

        return $this->progressSegment($progress, 8, false)
            .$this->coloredProgressSegment($competitor, 16, $this->competitorAccent($competitor))
            .$this->progressSegment($scenario, 24)
            .$this->progressSegment($subject, 64)
            .$this->coloredProgressSegment($metric, 32, 'yellow')
            .sprintf('<fg=green>%s</>', $operations);
    }

    /**
     * @param array<string, string> $rows
     */
    private function renderPlainDetailSectionBody(array $rows, int $width): string
    {
        $lines = [];

        foreach ($rows as $label => $value) {
            $lines[] = $this->plainDetailLine($label, $value, $width - 2);
        }

        return implode("\n", $lines);
    }

    private function coloredSectionTitle(string $title, string $accent): string
    {
        return sprintf(
            '<bg=%s;fg=black;options=bold> %s </>',
            $this->sectionAccent($title, $accent),
            $title,
        );
    }

    private function colorizeRenderedSections(string $rendered): string
    {
        $lines = explode("\n", $rendered);

        foreach ($lines as $index => $line) {
            if (!preg_match('/^([A-Za-z][A-Za-z0-9 ]+)$/', $line, $matches)) {
                continue;
            }

            $lines[$index] = sprintf(
                '<bg=%s;fg=black;options=bold> %s </>',
                $this->sectionAccent($matches[1]),
                $matches[1],
            );
        }

        return implode("\n", $lines);
    }

    private function sectionAccent(string $title, ?string $fallback = null): string
    {
        return match ($title) {
            'Environment' => 'cyan',
            'Running Benchmarks' => 'blue',
            'Results' => 'green',
            'Overall' => 'yellow',
            default => $fallback ?? $this->scenarioAccent($title),
        };
    }

    private function scenarioAccent(string $title): string
    {
        $palette = ['magenta', 'bright-blue', 'bright-cyan', 'bright-magenta', 'bright-green'];

        return $palette[crc32($title) % count($palette)];
    }

    private function progressSegment(string $value, int $width, bool $truncate = true): string
    {
        if ($truncate) {
            $value = $this->truncateProgressValue($value, max(4, $width - 6));
        }

        return sprintf(
            '%s %s ',
            $value,
            str_repeat('.', max(4, $width - mb_strlen($value) - 2)),
        );
    }

    private function coloredProgressSegment(string $value, int $width, string $color, bool $truncate = true): string
    {
        if ($truncate) {
            $value = $this->truncateProgressValue($value, max(4, $width - 6));
        }

        return sprintf(
            '<fg=%s>%s</> %s ',
            $color,
            $value,
            str_repeat('.', max(4, $width - mb_strlen($value) - 2)),
        );
    }

    private function competitorAccent(string $competitor): string
    {
        return match ($competitor) {
            'struct' => 'green',
            'bag' => 'magenta',
            'spatie' => 'cyan',
            default => 'blue',
        };
    }

    private function truncateProgressValue(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        if ($maxLength <= 3) {
            return mb_substr($value, 0, max(1, $maxLength));
        }

        return mb_substr($value, 0, $maxLength - 3).'...';
    }

    private function formatOperationsMetric(BenchmarkResult $result): string
    {
        return sprintf('%s ops/s', $this->formatMetricNumber($result->summary->operationsPerSecond, $this->progressOperationsDecimals));
    }

    private function formatConfiguredTimeMetric(BenchmarkResult $result): string
    {
        $value = match ($this->normalizedProgressMetric()) {
            Metric::Mean => $result->summary->mean,
            default => $result->summary->median,
        };

        [$scaled, $unit] = $this->convertNanoseconds($value);

        return sprintf('%s %s', $this->formatMetricNumber($scaled, $this->progressTimeDecimals), $unit);
    }

    private function progressMetricLabel(): string
    {
        return match ($this->normalizedProgressMetric()) {
            Metric::Mean => 'avg',
            default => 'median',
        };
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function convertNanoseconds(float $value): array
    {
        return match ($this->normalizedProgressTimeUnit()) {
            'ns' => [$value, 'ns'],
            'us', 'μs' => [$value / 1_000, 'μs'],
            'ms' => [$value / 1_000_000, 'ms'],
            's' => [$value / 1_000_000_000, 's'],
            default => [$value / 1_000, 'μs'],
        };
    }

    private function normalizedProgressMetric(): Metric
    {
        return $this->progressMetric;
    }

    private function normalizedProgressTimeUnit(): string
    {
        return mb_strtolower($this->progressTimeUnit->value);
    }

    private function formatMetricNumber(float $value, int $decimals): string
    {
        return number_format(
            $value,
            $decimals,
            $this->decimalSeparator,
            $this->thousandsSeparator,
        );
    }
}
