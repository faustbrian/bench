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
use Cline\Bench\Environment\CompatibilityMode;
use Cline\Bench\Environment\EnvironmentCompatibility;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Execution\BenchmarkRunner;
use Cline\Bench\Execution\BenchmarkSelection;
use Cline\Bench\Snapshot\RegressionEvaluator;
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

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function getcwd;
use function is_array;
use function is_string;
use function mb_rtrim;
use function realpath;
use function sprintf;
use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[AsCommand(name: 'snapshot:assert', description: 'Fail when regressions exceed the configured tolerance against a saved reference.')]
final class SnapshotAssertCommand extends Command
{
    #[Override()]
    protected function configure(): void
    {
        $this
            ->addArgument('against', InputArgument::OPTIONAL, 'Reference name')
            ->addArgument('path', InputArgument::OPTIONAL, 'Benchmark path')
            ->addOption('against', null, InputOption::VALUE_REQUIRED, 'Reference name')
            ->addOption('tolerance', null, InputOption::VALUE_REQUIRED, 'Allowed regression tolerance')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Only run benchmarks matching the given text')
            ->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks in the given group')
            ->addOption('competitor', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks for the given competitor');
    }

    #[Override()]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = BenchConfigLoader::load();
        $execution = $config->execution();
        $this->bootstrap($config);
        $current = new BenchmarkRunner()->runPath(
            $this->benchmarkPath($input, $config),
            $config,
            null,
            $this->selection($input),
        );
        $snapshot = $this->resolveReference(
            input: $input,
            config: $config,
            current: $current,
        );
        $referenceById = [];

        $environmentWarning = new EnvironmentCompatibility()->assess($snapshot->metadata);

        if ($environmentWarning !== null) {
            $output->writeln($environmentWarning);

            if ($execution->compatibilityMode === CompatibilityMode::Fail) {
                return self::FAILURE;
            }
        }

        foreach ($snapshot->results as $result) {
            $referenceById[$result->identifier()] = $result;
        }

        $evaluator = new RegressionEvaluator();
        $failed = false;

        foreach ($current as $result) {
            $baseline = $referenceById[$result->identifier()] ?? null;

            if ($baseline === null) {
                continue;
            }

            $metric = $result->regressionMetric ?? $execution->defaultRegressionMetric;
            $effectiveTolerance = $this->optionString($input, 'tolerance')
                ?? $result->regressionTolerance
                ?? $execution->defaultRegressionTolerance;

            $decision = $evaluator->evaluate(
                current: $result,
                baseline: $baseline,
                tolerance: $effectiveTolerance,
                metric: $metric,
            );

            $output->writeln(sprintf(
                '%s %s %s: %+.2f%% on %s vs tolerance %s',
                $result->scenario,
                $result->subject,
                $result->competitor,
                $decision->deltaPercentage,
                $metric->value,
                $effectiveTolerance,
            ));

            if ($decision->passed) {
                continue;
            }

            $failed = true;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
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
            filter: $this->optionString($input, 'filter'),
            groups: $this->optionStrings($input, 'group'),
            competitors: $this->optionStrings($input, 'competitor'),
        );
    }

    private function optionString(InputInterface $input, string $name): ?string
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

    private function againstReference(InputInterface $input): ?string
    {
        return $this->optionString($input, 'against') ?? $this->nullableArgumentString($input, 'against');
    }

    /**
     * @param list<BenchmarkResult> $current
     */
    private function resolveReference(InputInterface $input, BenchConfig $config, array $current): Snapshot
    {
        $storage = $config->storage();
        $comparison = $config->comparison();
        $resolver = new ReferenceResolver(
            $this->resolvePath($storage->snapshotPath),
            $this->resolvePath($storage->runPath),
        );
        $against = $this->againstReference($input);

        if ($against !== null) {
            return $resolver->resolve($against);
        }

        if ($comparison->scenarioReferences !== []) {
            return new ScenarioReferenceResolver($resolver)->resolve(
                scenarios: array_values(array_unique(array_map(
                    static fn (BenchmarkResult $result): string => $result->scenario,
                    $current,
                ))),
                scenarioReferences: $comparison->scenarioReferences,
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
