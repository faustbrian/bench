<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Console\BenchApplication;
use Symfony\Component\Console\Tester\CommandTester;

describe('bench init', function (): void {
    it('scaffolds a starter config and benchmark class', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-init-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $tester = new CommandTester(
                new BenchApplication()->find('init'),
            );

            expect($tester->execute([
                'command' => 'init',
            ]))->toBe(0)
                ->and(file_exists($workingDirectory.'/bench.php'))->toBeTrue()
                ->and(file_exists($workingDirectory.'/benchmarks/ExampleBench.php'))->toBeTrue()
                ->and((string) file_get_contents($workingDirectory.'/bench.php'))->toContain('BenchConfig::default()')
                ->and((string) file_get_contents($workingDirectory.'/benchmarks/ExampleBench.php'))->toContain('#[Bench')
                ->and($tester->getDisplay())->toContain('Created [bench.php]')
                ->and($tester->getDisplay())->toContain('Created [benchmarks/ExampleBench.php]');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });
});
