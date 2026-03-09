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
use Cline\Bench\Console\Concerns\ConfiguresReportOutput;
use Cline\Bench\Console\Concerns\FormatsResults;
use Cline\Bench\Environment\EnvironmentFingerprint;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Execution\BenchmarkRunner;
use Cline\Bench\Execution\BenchmarkSelection;
use Cline\Bench\Snapshot\Snapshot;
use Cline\Bench\Storage\ReferenceResolver;
use Cline\Bench\Storage\ScenarioReferenceResolver;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use const DATE_ATOM;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function getcwd;
use function gmdate;
use function is_array;
use function is_string;
use function mb_rtrim;
use function realpath;
use function sprintf;
use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[AsCommand(name: 'report', description: 'Render benchmark reports with optional reference comparison.')]
final class ReportCommand extends Command
{
    use FormatsResults;
    use ConfiguresReportOutput {
        ConfiguresReportOutput::preferredCompetitors insteadof FormatsResults;
        ConfiguresReportOutput::competitorAliases insteadof FormatsResults;
        ConfiguresReportOutput::comparisonReference insteadof FormatsResults;
        ConfiguresReportOutput::decimalSeparator insteadof FormatsResults;
        ConfiguresReportOutput::thousandsSeparator insteadof FormatsResults;
        ConfiguresReportOutput::rawNumberDecimals insteadof FormatsResults;
        ConfiguresReportOutput::durationDecimals insteadof FormatsResults;
        ConfiguresReportOutput::operationsDecimals insteadof FormatsResults;
        ConfiguresReportOutput::ratioDecimals insteadof FormatsResults;
        ConfiguresReportOutput::percentageDecimals insteadof FormatsResults;
        ConfiguresReportOutput::deltaPercentageDecimals insteadof FormatsResults;
        ConfiguresReportOutput::significanceEnabled insteadof FormatsResults;
        ConfiguresReportOutput::significanceAlpha insteadof FormatsResults;
        ConfiguresReportOutput::significanceMinimumSamples insteadof FormatsResults;
    }

    #[Override()]
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Benchmark path')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json, md, csv)')
            ->addOption('against', null, InputOption::VALUE_REQUIRED, 'Optional reference name to compare against')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Only run benchmarks matching the given text')
            ->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks in the given group')
            ->addOption('competitor', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks for the given competitor')
            ->addOption('no-significance', null, InputOption::VALUE_NONE, 'Disable significance calculation in rendered reports');
    }

    #[Override()]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = BenchConfigLoader::load();
        $reporting = $config->reporting();
        $storage = $config->storage();
        $comparison = $config->comparison();
        $this->initializeReportOutput($config, $this->flag($input, 'no-significance'));
        $this->bootstrap($config);
        $selection = $this->selection($input);
        $results = new BenchmarkRunner()->runPath($this->benchmarkPath($input, $config), $config, null, $selection);
        $against = $this->nullableOptionString($input, 'against');
        $format = $this->nullableOptionString($input, 'format') ?? $reporting->defaultReportFormat->value;
        $metadata = $this->reportMetadata('report', $config, $selection);

        if ($against === null && $comparison->scenarioReferences === []) {
            $output->writeln(match ($format) {
                'json' => $this->asJson($results, $metadata),
                'csv' => $this->asCsv($results),
                'md' => $this->asMarkdown($results, $metadata),
                default => $this->asTable($results, $metadata),
            });

            return self::SUCCESS;
        }

        $snapshot = $this->resolveReference(
            against: $against,
            config: $config,
            results: $results,
            resolver: new ReferenceResolver(
                $this->resolvePath($storage->snapshotPath),
                $this->resolvePath($storage->runPath),
            ),
        );
        $referenceName = $against ?? $snapshot->name;

        $output->writeln(match ($format) {
            'json' => $this->asComparisonJson($results, $snapshot->results, [
                'schema_version' => 1,
                'report_type' => 'comparison',
                'generated_at' => gmdate(DATE_ATOM),
                'selection' => $selection->toArray(),
                'current' => $metadata,
                'reference' => $snapshot->metadata,
                'reference_name' => $referenceName,
            ]),
            'csv' => $this->asComparisonCsv($results, $snapshot->results),
            'md' => $this->asComparisonMarkdown($results, $snapshot->results, [
                'current' => $metadata,
                'reference' => $snapshot->metadata,
                'reference_name' => $referenceName,
            ]),
            default => $this->asComparisonTable($results, $snapshot->results, [
                'current' => $metadata,
                'reference' => $snapshot->metadata,
                'reference_name' => $referenceName,
            ]),
        });

        return self::SUCCESS;
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    private function resolveReference(?string $against, BenchConfig $config, array $results, ReferenceResolver $resolver): Snapshot
    {
        if ($against !== null) {
            return $resolver->resolve($against);
        }

        return new ScenarioReferenceResolver($resolver)->resolve(
            scenarios: array_values(array_unique(array_map(
                static fn (BenchmarkResult $result): string => $result->scenario,
                $results,
            ))),
            scenarioReferences: $config->scenarioReferences,
        );
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
                'significance_enabled' => $this->significanceEnabled(),
                'significance_alpha' => $this->significanceAlpha(),
                'significance_minimum_samples' => $this->significanceMinimumSamples(),
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
}
