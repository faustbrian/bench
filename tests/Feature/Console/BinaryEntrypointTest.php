<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

describe('bench binary entrypoint', function (): void {
    it('boots correctly from an installed vendor package path', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-bin-'.bin2hex(random_bytes(8));
        $projectDirectory = $workingDirectory.'/project';
        $packageDirectory = $projectDirectory.'/vendor/cline/bench';
        $binaryPath = $packageDirectory.'/bin/bench';
        $autoloadPath = $projectDirectory.'/vendor/autoload.php';

        mkdir($packageDirectory.'/bin', 0o755, true);
        copy(__DIR__.'/../../../bin/bench', $binaryPath);

        file_put_contents($autoloadPath, sprintf(
            "<?php declare(strict_types=1);\n\nrequire %s;\n",
            var_export(__DIR__.'/../../../vendor/autoload.php', true),
        ));

        chmod($binaryPath, 0o755);

        $output = [];
        $exitCode = 1;

        exec(sprintf('php %s list --raw 2>&1', escapeshellarg($binaryPath)), $output, $exitCode);

        expect($exitCode)->toBe(0)
            ->and(implode("\n", $output))->toContain('run');
    });

    it('can run benchmarks from an installed vendor bin entrypoint', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-vendor-bin-'.bin2hex(random_bytes(8));
        $projectDirectory = $workingDirectory.'/project';
        $packageDirectory = $projectDirectory.'/vendor/cline/bench';
        $packageBinaryPath = $packageDirectory.'/bin/bench';
        $vendorBinaryPath = $projectDirectory.'/vendor/bin/bench';
        $autoloadPath = $projectDirectory.'/vendor/autoload.php';
        $benchmarkDirectory = $projectDirectory.'/benchmarks';

        mkdir($packageDirectory.'/bin', 0o755, true);
        mkdir($projectDirectory.'/vendor/bin', 0o755, true);
        mkdir($benchmarkDirectory, 0o755, true);

        copy(__DIR__.'/../../../bin/bench', $packageBinaryPath);

        file_put_contents($autoloadPath, sprintf(
            "<?php declare(strict_types=1);\n\nrequire %s;\n",
            var_export(__DIR__.'/../../../vendor/autoload.php', true),
        ));

        file_put_contents($vendorBinaryPath, <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/../cline/bench/bin/bench';
PHP);

        file_put_contents($projectDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()
    ->withProcessIsolation(true)
    ->withBenchmarkPath('benchmarks');
PHP);

        file_put_contents($benchmarkDirectory.'/InstalledBench.php', <<<'PHP'
<?php declare(strict_types=1);

namespace InstalledBinaryBench;

use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Threshold;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ThresholdOperator;

final class InstalledBench
{
    #[Bench('smoke')]
    #[Iterations(1)]
    #[Revolutions(1)]
    #[Threshold(Metric::Median, ThresholdOperator::GreaterThan, 0.0)]
    public function benchSmoke(): void
    {
        $value = 1 + 1;

        if ($value === 0) {
            throw new \RuntimeException('unreachable');
        }
    }
}
PHP);

        chmod($packageBinaryPath, 0o755);
        chmod($vendorBinaryPath, 0o755);

        $output = [];
        $exitCode = 1;

        exec(
            sprintf(
                'cd %s && php vendor/bin/bench run --no-progress 2>&1',
                escapeshellarg($projectDirectory),
            ),
            $output,
            $exitCode,
        );

        expect($exitCode)->toBe(0)
            ->and(implode("\n", $output))->toContain('Results')
            ->and(implode("\n", $output))->toContain('smoke');
    });
});
