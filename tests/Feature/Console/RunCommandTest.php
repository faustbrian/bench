<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Console\BenchApplication;
use Symfony\Component\Console\Tester\CommandTester;

describe('bench run', function (): void {
    it('renders a json report with rich metrics', function (): void {
        $application = new BenchApplication();
        $command = $application->find('run');
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([
            'command' => 'run',
            'path' => __DIR__.'/../../Fixtures/Benchmarks',
            '--format' => 'json',
        ]);

        expect($statusCode)->toBe(0);

        $payload = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        expect($payload)->toHaveKeys(['results', 'comparison', 'metadata'])
            ->and($payload['metadata'])->toHaveKeys([
                'schema_version',
                'report_type',
                'generated_at',
                'environment',
                'selection',
            ])
            ->and($payload['metadata']['report_type'])->toBe('run')
            ->and($payload['results'])->toHaveCount(10)
            ->and($payload['results'][0]['summary'])->toHaveKeys([
                'median',
                'percentile95',
                'percentile99',
                'operations_per_second',
            ])
            ->and($payload['results'][0])->toHaveKeys([
                'parameters',
                'groups',
                'assertions',
                'regression',
            ])
            ->and($payload['comparison']['rows'][0])->toHaveKeys([
                'winner',
                'delta_percentage',
                'significance',
            ]);
    });

    it('filters benchmarks by competitor, group, and subject text', function (): void {
        $application = new BenchApplication();
        $tester = new CommandTester($application->find('run'));

        expect($tester->execute([
            'command' => 'run',
            'path' => __DIR__.'/../../Fixtures/Benchmarks',
            '--format' => 'json',
            '--competitor' => ['bench'],
            '--group' => ['dto'],
            '--filter' => 'transform',
        ]))->toBe(0);

        $payload = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        expect($payload['results'])->toHaveCount(2)
            ->and($payload['metadata']['selection'])->toBe([
                'filter' => 'transform',
                'groups' => ['dto'],
                'competitors' => ['bench'],
            ])
            ->and(array_unique(array_column($payload['results'], 'competitor')))->toBe(['bench'])
            ->and(array_unique(array_column($payload['results'], 'scenario')))->toBe(['dto-transform']);
    });

    it('uses the configured default report format when no format option is given', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-run-default-format-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()->withDefaultReportFormat('md');
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $tester = new CommandTester($application->find('run'));

            expect($tester->execute([
                'command' => 'run',
                'path' => __DIR__.'/../../Fixtures/Benchmarks',
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('## Comparison')
                ->and($tester->getDisplay())->toContain('### Dto transform')
                ->and($tester->getDisplay())->toContain('| Benchmark |')
                ->and($tester->getDisplay())->toContain('| Winner | Closest Gap | Closest Gain |');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('uses the configured preferred competitor order in comparison tables', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-run-order-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()->withPreferredCompetitors(['spatie', 'struct']);
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $tester = new CommandTester($application->find('run'));

            expect($tester->execute([
                'command' => 'run',
                'path' => __DIR__.'/../../Fixtures/BalooBenchmarks',
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('│  Spatie │  Struct │')
                ->and($tester->getDisplay())->toContain('│ Spatie Ops/s │ Struct Ops/s │');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('uses configured competitor aliases in comparison tables', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-run-aliases-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()
    ->withCompetitorAliases([
        'struct' => 'Baloo',
        'spatie' => 'Spatie Data',
    ]);
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $tester = new CommandTester($application->find('run'));

            expect($tester->execute([
                'command' => 'run',
                'path' => __DIR__.'/../../Fixtures/BalooBenchmarks',
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('Baloo')
                ->and($tester->getDisplay())->toContain('Spatie Data')
                ->and($tester->getDisplay())->toContain('Baloo Ops/s')
                ->and($tester->getDisplay())->toContain('Spatie Data Ops/s');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('renders a comparison-first table with progress updates during execution', function (): void {
        $application = new BenchApplication();
        $command = $application->find('run');
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([
            'command' => 'run',
            'path' => __DIR__.'/../../Fixtures/BalooBenchmarks',
        ]);

        expect($statusCode)->toBe(0)
            ->and($tester->getDisplay())->toContain('Running Benchmarks')
            ->and($tester->getDisplay())->toContain('Environment')
            ->and($tester->getDisplay())->toContain('PHP')
            ->and($tester->getDisplay())->toContain('Process Isolation')
            ->and($tester->getDisplay())->toMatch('/\s+\d+\/24\s+\.+\s+struct/s')
            ->and($tester->getDisplay())->toMatch('/24\/24\s+\.+\s+(struct|spatie)/s')
            ->and($tester->getDisplay())->toContain('Environment')
            ->and($tester->getDisplay())->toContain('Running Benchmarks')
            ->and($tester->getDisplay())->toContain('Results')
            ->and($tester->getDisplay())->toContain('Overall')
            ->and($tester->getDisplay())->toMatch('/\.{4,}/')
            ->and($tester->getDisplay())->toMatch('/ops\/s\s*\n\s*\n\s*Results/s')
            ->and($tester->getDisplay())->toMatch('/Results\s*\n\s*\n\s*Overall/s')
            ->and($tester->getDisplay())->toContain('baloo-data')
            ->and($tester->getDisplay())->toContain('collection-transformation')
            ->and($tester->getDisplay())->toContain('median ')
            ->and($tester->getDisplay())->toContain(' μs')
            ->and($tester->getDisplay())->toContain(' ops/s')
            ->and($tester->getDisplay())->not->toContain('------------------')
            ->and($tester->getDisplay())->toContain('Winner')
            ->and($tester->getDisplay())->toContain('Closest Gap')
            ->and($tester->getDisplay())->toContain('Closest Gain')
            ->and($tester->getDisplay())->toContain('Geometric mean spread')
            ->and($tester->getDisplay())->toContain('average gap')
            ->and($tester->getDisplay())->toContain('slower than fastest')
            ->and($tester->getDisplay())->not->toContain('Legend: lower time is better')
            ->and($tester->getDisplay())->not->toContain('Comparison ................................')
            ->and($tester->getDisplay())->toContain('struct')
            ->and($tester->getDisplay())->toContain('spatie');
    });

    it('can suppress progress output while keeping the final report', function (): void {
        $application = new BenchApplication();
        $tester = new CommandTester($application->find('run'));

        expect($tester->execute([
            'command' => 'run',
            'path' => __DIR__.'/../../Fixtures/BalooBenchmarks',
            '--no-progress' => true,
        ]))->toBe(0)
            ->and($tester->getDisplay())->not->toContain('Running Benchmarks')
            ->and($tester->getDisplay())->not->toContain('1/24')
            ->and($tester->getDisplay())->toContain('Environment')
            ->and($tester->getDisplay())->toContain('Overall');
    });

    it('uses configured progress metric and time unit for live progress output', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-run-progress-config-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()
    ->withProgressMetric('average')
    ->withProgressTimeUnit('ms');
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $tester = new CommandTester($application->find('run'));

            expect($tester->execute([
                'command' => 'run',
                'path' => __DIR__.'/../../Fixtures/BalooBenchmarks',
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('avg ')
                ->and($tester->getDisplay())->toContain(' ms')
                ->and($tester->getDisplay())->toMatch('/\.{4,}/')
                ->and($tester->getDisplay())->toContain(' ops/s');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('uses configured locale and decimal precision for progress and tables', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-run-number-format-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()
    ->withNumberSeparators(decimalSeparator: ',', thousandsSeparator: '.')
    ->withDurationDecimals(0)
    ->withOperationsDecimals(0)
    ->withProgressDecimals(timeDecimals: 0, operationsDecimals: 0)
    ->withPercentageDecimals(2)
    ->withRatioDecimals(3);
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $tester = new CommandTester($application->find('run'));

            expect($tester->execute([
                'command' => 'run',
                'path' => __DIR__.'/../../Fixtures/BalooBenchmarks',
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('median ')
                ->and($tester->getDisplay())->toContain(' μs')
                ->and($tester->getDisplay())->toMatch('/median \d+ μs/')
                ->and($tester->getDisplay())->toMatch('/\d{1,3}(?:\.\d{3})+ ops\/s/')
                ->and($tester->getDisplay())->toMatch('/\d+,\d{3}x/')
                ->and($tester->getDisplay())->toMatch('/\d+,\d{2}%/');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('uses the configured comparison reference in summary tables', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-run-comparison-reference-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()->withComparisonReference('slowest');
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $tester = new CommandTester($application->find('run'));

            expect($tester->execute([
                'command' => 'run',
                'path' => __DIR__.'/../../Fixtures/BalooBenchmarks',
                '--no-progress' => true,
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('Field Spread')
                ->and($tester->getDisplay())->toContain('Fastest Gain')
                ->and($tester->getDisplay())->not->toContain('Closest Gap')
                ->and($tester->getDisplay())->not->toContain('Closest Gain');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('fails when benchmark assertions fail', function (): void {
        $application = new BenchApplication();
        $command = $application->find('run');
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([
            'command' => 'run',
            'path' => __DIR__.'/../../Fixtures/FailingBenchmarks/FailingAssertBench.php',
        ]);

        expect($statusCode)->toBe(1)
            ->and($tester->getDisplay())->toContain('assertions failed');
    });
});
