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
use Cline\Bench\Environment\EnvironmentFingerprint;
use Cline\Bench\Execution\BenchmarkRunner;
use Cline\Bench\Execution\BenchmarkSelection;
use Cline\Bench\Snapshot\SnapshotStore;
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
#[AsCommand(name: 'snapshot:save', description: 'Run benchmarks and save a named snapshot.')]
final class SnapshotSaveCommand extends Command
{
    #[Override()]
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Snapshot name')
            ->addArgument('path', InputArgument::OPTIONAL, 'Benchmark path')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Only run benchmarks matching the given text')
            ->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks in the given group')
            ->addOption('competitor', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only run benchmarks for the given competitor');
    }

    #[Override()]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = BenchConfigLoader::load();
        $this->bootstrap($config);
        $selection = $this->selection($input);
        $results = new BenchmarkRunner()->runPath($this->benchmarkPath($input, $config), $config, null, $selection);
        $name = $this->argumentString($input, 'name');

        new SnapshotStore($this->resolvePath($config->snapshotPath))->save(
            name: $name,
            results: $results,
            metadata: [
                'schema_version' => 1,
                'report_type' => 'snapshot',
                'generated_at' => gmdate(DATE_ATOM),
                'environment' => EnvironmentFingerprint::capture()->toArray(),
                'selection' => $selection->toArray(),
                'settings' => [
                    'process_isolation' => $config->processIsolation,
                    'default_iterations' => $config->defaultIterations,
                    'default_revolutions' => $config->defaultRevolutions,
                    'default_warmup_iterations' => $config->defaultWarmupIterations,
                ],
            ],
        );

        $output->writeln(sprintf('Saved snapshot [%s].', $name));

        return self::SUCCESS;
    }

    private function argumentString(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

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
        $filter = $input->getOption('filter');

        return new BenchmarkSelection(
            filter: is_string($filter) ? $filter : null,
            groups: $this->optionStrings($input, 'group'),
            competitors: $this->optionStrings($input, 'competitor'),
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
