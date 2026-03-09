<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Console\Commands;

use Cline\Bench\Comparison\ComparePolicyEvaluator;
use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Configuration\BenchConfigLoader;
use Cline\Bench\Console\Concerns\ConfiguresReportOutput;
use Cline\Bench\Console\Concerns\FormatsResults;
use Cline\Bench\Environment\CompatibilityMode;
use Cline\Bench\Environment\EnvironmentCompatibility;
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
#[AsCommand(name: 'compare', description: 'Compare a benchmark run against a saved reference.')]
final class CompareCommand extends Command
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
            ->addArgument('against', InputArgument::OPTIONAL, 'Reference name')
            ->addArgument('path', InputArgument::OPTIONAL, 'Benchmark path')
            ->addOption('against', null, InputOption::VALUE_REQUIRED, 'Reference name')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json, md, csv)')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Only run benchmarks matching the given text')
            ->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks in the given group')
            ->addOption('competitor', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks for the given competitor')
            ->addOption('fail-on-winner-change', null, InputOption::VALUE_NONE, 'Fail when a benchmark winner changes versus the reference suite')
            ->addOption('min-reference-gap', null, InputOption::VALUE_REQUIRED, 'Require each current reference gap to stay above the given threshold')
            ->addOption('no-significance', null, InputOption::VALUE_NONE, 'Disable significance calculation in rendered reports and policies');
    }

    #[Override()]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = BenchConfigLoader::load();
        $reporting = $config->reporting();
        $execution = $config->execution();
        $storage = $config->storage();
        $this->initializeReportOutput($config, $this->flag($input, 'no-significance'));
        $this->bootstrap($config);
        $selection = $this->selection($input);
        $current = new BenchmarkRunner()->runPath($this->benchmarkPath($input, $config), $config, null, $selection);
        $resolver = new ReferenceResolver(
            $this->resolvePath($storage->snapshotPath),
            $this->resolvePath($storage->runPath),
        );
        $snapshot = $this->resolveReference($input, $config, $current, $resolver);
        $against = $snapshot->name;

        $environmentWarning = new EnvironmentCompatibility()->assess($snapshot->metadata);

        if ($environmentWarning !== null) {
            $output->writeln($environmentWarning);

            if ($execution->compatibilityMode === CompatibilityMode::Fail) {
                return self::FAILURE;
            }
        }

        $policy = new ComparePolicyEvaluator()->evaluate(
            current: $current,
            baseline: $snapshot->results,
            failOnWinnerChange: $this->flag($input, 'fail-on-winner-change'),
            minimumReferenceGap: $this->nullableFloatOption($input, 'min-reference-gap'),
            comparisonReference: $this->comparisonReference(),
        );

        $output->writeln(match ($this->nullableOptionString($input, 'format') ?? $reporting->defaultReportFormat->value) {
            'json' => $this->asComparisonJson($current, $snapshot->results, [
                'schema_version' => 1,
                'report_type' => 'comparison',
                'generated_at' => gmdate(DATE_ATOM),
                'selection' => $selection->toArray(),
                'policy' => [
                    'passed' => $policy->passed,
                    'violations' => $policy->violations,
                ],
                'current' => [
                    'environment' => EnvironmentFingerprint::capture()->toArray(),
                    'settings' => [
                        'process_isolation' => $execution->processIsolation,
                        'default_iterations' => $execution->defaultIterations,
                        'default_revolutions' => $execution->defaultRevolutions,
                        'default_warmup_iterations' => $execution->defaultWarmupIterations,
                        'significance_enabled' => $this->significanceEnabled(),
                        'significance_alpha' => $this->significanceAlpha(),
                        'significance_minimum_samples' => $this->significanceMinimumSamples(),
                    ],
                ],
                'reference' => $snapshot->metadata,
                'reference_name' => $against,
            ]),
            'csv' => $this->asComparisonCsv($current, $snapshot->results),
            'md' => $this->asComparisonMarkdown($current, $snapshot->results, [
                'current' => [
                    'environment' => EnvironmentFingerprint::capture()->toArray(),
                    'settings' => [
                        'process_isolation' => $execution->processIsolation,
                        'default_iterations' => $execution->defaultIterations,
                        'default_revolutions' => $execution->defaultRevolutions,
                        'default_warmup_iterations' => $execution->defaultWarmupIterations,
                        'significance_enabled' => $this->significanceEnabled(),
                        'significance_alpha' => $this->significanceAlpha(),
                        'significance_minimum_samples' => $this->significanceMinimumSamples(),
                    ],
                    'selection' => $selection->toArray(),
                ],
                'reference' => $snapshot->metadata,
                'reference_name' => $against,
            ]),
            default => $this->asComparisonTable($current, $snapshot->results, [
                'current' => [
                    'environment' => EnvironmentFingerprint::capture()->toArray(),
                    'settings' => [
                        'process_isolation' => $execution->processIsolation,
                        'default_iterations' => $execution->defaultIterations,
                        'default_revolutions' => $execution->defaultRevolutions,
                        'default_warmup_iterations' => $execution->defaultWarmupIterations,
                        'significance_enabled' => $this->significanceEnabled(),
                        'significance_alpha' => $this->significanceAlpha(),
                        'significance_minimum_samples' => $this->significanceMinimumSamples(),
                    ],
                    'selection' => $selection->toArray(),
                ],
                'reference' => $snapshot->metadata,
                'reference_name' => $against,
            ]),
        });

        if ($policy->passed) {
            return self::SUCCESS;
        }

        $output->writeln('Compare policies failed:');

        foreach ($policy->violations as $violation) {
            $output->writeln(sprintf('- %s', $violation));
        }

        return self::FAILURE;
    }

    private function nullableArgumentString(InputInterface $input, string $name): ?string
    {
        $value = $input->getArgument($name);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Argument [%s] must be a string.', $name));
        }

        return $value;
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

    private function selection(InputInterface $input): BenchmarkSelection
    {
        return new BenchmarkSelection(
            filter: $this->nullableOptionString($input, 'filter'),
            groups: $this->optionStrings($input, 'group'),
            competitors: $this->optionStrings($input, 'competitor'),
        );
    }

    private function againstReference(InputInterface $input): ?string
    {
        return $this->nullableOptionString($input, 'against') ?? $this->nullableArgumentString($input, 'against');
    }

    private function flag(InputInterface $input, string $name): bool
    {
        return (bool) $input->getOption($name);
    }

    private function nullableFloatOption(InputInterface $input, string $name): ?float
    {
        $value = $this->nullableOptionString($input, $name);

        return $value === null ? null : (float) $value;
    }

    /**
     * @param list<BenchmarkResult> $current
     */
    private function resolveReference(InputInterface $input, BenchConfig $config, array $current, ReferenceResolver $resolver): Snapshot
    {
        $against = $this->againstReference($input);

        if ($against !== null) {
            return $resolver->resolve($against);
        }

        if ($config->scenarioReferences !== []) {
            return new ScenarioReferenceResolver($resolver)->resolve(
                scenarios: array_values(array_unique(array_map(
                    static fn (BenchmarkResult $result): string => $result->scenario,
                    $current,
                ))),
                scenarioReferences: $config->scenarioReferences,
            );
        }

        throw new InvalidArgumentException('Either the [against] argument, the [--against] option, or configured scenario references are required.');
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
