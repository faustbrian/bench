<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Console\BenchApplication;
use Symfony\Component\Console\Tester\CommandTester;

describe('snapshot commands', function (): void {
    it('saves, compares, and asserts snapshots', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-cli-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/Benchmarks';

            $saveTester = new CommandTester($application->find('snapshot:save'));
            $compareTester = new CommandTester($application->find('compare'));
            $assertTester = new CommandTester($application->find('snapshot:assert'));
            $markdownRunTester = new CommandTester($application->find('run'));

            expect($saveTester->execute([
                'command' => 'snapshot:save',
                'name' => 'baseline',
                'path' => $fixturePath,
            ]))->toBe(0);

            expect(file_exists($workingDirectory.'/.bench/snapshots/baseline.json'))->toBeTrue()
                ->and(file_exists($workingDirectory.'/.bench/snapshots/latest.json'))->toBeTrue();

            expect($compareTester->execute([
                'command' => 'compare',
                'against' => 'snapshot:latest',
                'path' => $fixturePath,
            ]))->toBe(0)
                ->and($compareTester->getDisplay())->toContain('Baseline')
                ->and($compareTester->getDisplay())->toContain('Delta %')
                ->and($compareTester->getDisplay())->toContain('Winner')
                ->and($compareTester->getDisplay())->toContain('Significance');

            expect($assertTester->execute([
                'command' => 'snapshot:assert',
                'against' => 'baseline',
                'path' => $fixturePath,
                '--tolerance' => '10000%',
            ]))->toBe(0)
                ->and($assertTester->getDisplay())->toContain('tolerance 10000%');

            expect($markdownRunTester->execute([
                'command' => 'run',
                'path' => $fixturePath,
                '--format' => 'md',
            ]))->toBe(0)
                ->and($markdownRunTester->getDisplay())->toContain('## Comparison')
                ->and($markdownRunTester->getDisplay())->toContain('### Dto transform')
                ->and($markdownRunTester->getDisplay())->toContain('| Benchmark |')
                ->and($markdownRunTester->getDisplay())->toContain('| Winner | Closest Reference Gap | Closest Reference Gain |');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('uses bench.php config and fails on incompatible environments when configured', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-cli-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Environment\CompatibilityMode;

return BenchConfig::default()
    ->withSnapshotPath('.benchmarks/snapshots')
    ->withCompatibilityMode(CompatibilityMode::Fail);
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/Benchmarks';

            $saveTester = new CommandTester($application->find('snapshot:save'));
            $assertTester = new CommandTester($application->find('snapshot:assert'));

            expect($saveTester->execute([
                'command' => 'snapshot:save',
                'name' => 'baseline',
                'path' => $fixturePath,
            ]))->toBe(0);

            expect(file_exists($workingDirectory.'/.benchmarks/snapshots/baseline.json'))->toBeTrue();

            $snapshotPath = $workingDirectory.'/.benchmarks/snapshots/baseline.json';

            /**
             * @var array{
             *     metadata: array{
             *         environment: array{
             *             php_version: string
             *         }
             *     }
             * } $snapshot
             */
            $snapshot = json_decode((string) file_get_contents($snapshotPath), true, flags: \JSON_THROW_ON_ERROR);
            $snapshot['metadata']['environment']['php_version'] = '0.0.0-test';
            file_put_contents($snapshotPath, json_encode($snapshot, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

            expect($assertTester->execute([
                'command' => 'snapshot:assert',
                'against' => 'baseline',
                'path' => $fixturePath,
            ]))->toBe(1)
                ->and($assertTester->getDisplay())->toContain('environment mismatch');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('saves named runs and compares against saved runs', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-runs-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/Benchmarks';

            $runTester = new CommandTester($application->find('run'));
            $compareTester = new CommandTester($application->find('compare'));
            $reportTester = new CommandTester($application->find('report'));

            expect($runTester->execute([
                'command' => 'run',
                'path' => $fixturePath,
                '--save' => 'candidate',
            ]))->toBe(0);

            expect(file_exists($workingDirectory.'/.bench/runs/candidate.json'))->toBeTrue()
                ->and(file_exists($workingDirectory.'/.bench/runs/latest.json'))->toBeTrue();

            expect($compareTester->execute([
                'command' => 'compare',
                'against' => 'run:latest',
                'path' => $fixturePath,
            ]))->toBe(0)
                ->and($compareTester->getDisplay())->toContain('Baseline');

            expect($reportTester->execute([
                'command' => 'report',
                'path' => $fixturePath,
                '--against' => 'run:candidate',
                '--format' => 'json',
            ]))->toBe(0)
                ->and($reportTester->getDisplay())->toContain('"comparisons"')
                ->and($reportTester->getDisplay())->toContain('"baseline"')
                ->and($reportTester->getDisplay())->toContain('"metadata"')
                ->and($reportTester->getDisplay())->toContain('"schema_version"')
                ->and($reportTester->getDisplay())->toContain('"report_type"');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('renders environment context in markdown reports and respects selectors in snapshot thresholds', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-report-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/BalooBenchmarks';

            $saveTester = new CommandTester($application->find('snapshot:save'));
            $reportTester = new CommandTester($application->find('report'));
            $assertTester = new CommandTester($application->find('snapshot:assert'));

            expect($saveTester->execute([
                'command' => 'snapshot:save',
                'name' => 'baseline',
                'path' => $fixturePath,
            ]))->toBe(0);

            expect($reportTester->execute([
                'command' => 'report',
                'path' => $fixturePath,
                '--format' => 'md',
                '--competitor' => ['struct'],
                '--group' => ['comparison'],
            ]))->toBe(0)
                ->and($reportTester->getDisplay())->toContain('## Environment')
                ->and($reportTester->getDisplay())->toContain('- PHP:')
                ->and($reportTester->getDisplay())->toContain('## Selection')
                ->and($reportTester->getDisplay())->toContain('- Competitors: `struct`')
                ->and($reportTester->getDisplay())->toContain('- Groups: `comparison`');

            expect($assertTester->execute([
                'command' => 'snapshot:assert',
                'against' => 'baseline',
                'path' => $fixturePath,
                '--competitor' => ['struct'],
                '--tolerance' => '10000%',
            ]))->toBe(0)
                ->and($assertTester->getDisplay())->toContain(' struct')
                ->and($assertTester->getDisplay())->not->toContain(' spatie');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('uses configured scenario baselines when no explicit against reference is provided', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-pinned-baselines-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        file_put_contents($workingDirectory.'/bench.php', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Bench\Configuration\BenchConfig;

return BenchConfig::default()
    ->withScenarioBaselines([
        'dto-transform' => 'transform-baseline',
    ]);
PHP);

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/Benchmarks';

            expect(
                new CommandTester($application->find('snapshot:save'))->execute([
                    'command' => 'snapshot:save',
                    'name' => 'transform-baseline',
                    'path' => __DIR__.'/../../Fixtures/Benchmarks/TransformBench.php',
                ]),
            )->toBe(0);

            $compareTester = new CommandTester($application->find('compare'));
            $assertTester = new CommandTester($application->find('snapshot:assert'));
            $reportTester = new CommandTester($application->find('report'));

            expect($compareTester->execute([
                'command' => 'compare',
                'path' => $fixturePath,
                '--competitor' => ['bench'],
                '--format' => 'json',
            ]))->toBe(0)
                ->and($compareTester->getDisplay())->toContain('"baseline_name": "pinned"');

            expect($reportTester->execute([
                'command' => 'report',
                'path' => $fixturePath,
                '--competitor' => ['bench'],
                '--format' => 'md',
            ]))->toBe(0)
                ->and($reportTester->getDisplay())->toContain('## Environment')
                ->and($reportTester->getDisplay())->toContain('- Baseline: `pinned`');

            expect($assertTester->execute([
                'command' => 'snapshot:assert',
                'path' => $fixturePath,
                '--competitor' => ['bench'],
                '--tolerance' => '10000%',
            ]))->toBe(0)
                ->and($assertTester->getDisplay())->toContain('dto-transform');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('fails compare when the winner changes or the reference gap drops below a required threshold', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-compare-policies-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/BalooBenchmarks';

            expect(
                new CommandTester($application->find('snapshot:save'))->execute([
                    'command' => 'snapshot:save',
                    'name' => 'baseline',
                    'path' => $fixturePath,
                ]),
            )->toBe(0);

            $snapshotPath = $workingDirectory.'/.bench/snapshots/baseline.json';

            /**
             * @var array{
             *     results: list<array{
             *         scenario: string,
             *         subject: string,
             *         competitor: string,
             *         summary: array{median: float}
             *     }>
             * } $snapshot
             */
            $snapshot = json_decode((string) file_get_contents($snapshotPath), true, flags: \JSON_THROW_ON_ERROR);

            foreach ($snapshot['results'] as &$result) {
                if ($result['scenario'] === 'baloo-data' && $result['subject'] === 'collection-transformation' && $result['competitor'] === 'spatie') {
                    $result['summary']['median'] = 50_000.0;
                }

                if ($result['scenario'] !== 'baloo-data') {
                    continue;
                }

                if ($result['subject'] !== 'collection-transformation') {
                    continue;
                }

                if ($result['competitor'] !== 'struct') {
                    continue;
                }

                $result['summary']['median'] = 200_000.0;
            }

            file_put_contents($snapshotPath, json_encode($snapshot, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

            $compareTester = new CommandTester($application->find('compare'));

            expect($compareTester->execute([
                'command' => 'compare',
                'against' => 'baseline',
                'path' => $fixturePath,
                '--fail-on-winner-change' => true,
                '--min-reference-gap' => '10',
            ]))->toBe(1)
                ->and($compareTester->getDisplay())->toContain('Compare policies failed')
                ->and($compareTester->getDisplay())->toContain('winner changed')
                ->and($compareTester->getDisplay())->toContain('reference gap');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });
});
