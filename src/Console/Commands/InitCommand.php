<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Console\Commands;

use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[AsCommand(name: 'init', description: 'Scaffold a bench.php config and example benchmark.')]
final class InitCommand extends Command
{
    #[Override()]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->ensureDirectory('benchmarks');

        $created = [];

        if (!$this->writeIfMissing('bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()
    ->withBenchmarkPath('benchmarks')
    ->withBootstrapPath('vendor/autoload.php');
PHP)) {
            $output->writeln('Skipped [bench.php].');
        } else {
            $created[] = 'bench.php';
        }

        if (!$this->writeIfMissing('benchmarks/ExampleBench.php', <<<'PHP'
<?php declare(strict_types=1);

namespace Benchmarks;

use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Revs;
use Cline\Bench\Attributes\Scenario;

use function strlen;

#[Scenario('example')]
#[Competitor('app')]
final class ExampleBench
{
    #[Bench('string-length')]
    #[Iterations(5)]
    #[Revs(1000)]
    public function benchStringLength(): void
    {
        strlen('bench');
    }
}
PHP)) {
            $output->writeln('Skipped [benchmarks/ExampleBench.php].');
        } else {
            $created[] = 'benchmarks/ExampleBench.php';
        }

        foreach ($created as $path) {
            $output->writeln(sprintf('Created [%s].', $path));
        }

        return self::SUCCESS;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $path));
        }
    }

    private function writeIfMissing(string $path, string $contents): bool
    {
        if (file_exists($path)) {
            return false;
        }

        file_put_contents($path, $contents);

        return true;
    }
}
