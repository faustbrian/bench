<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Console\BenchApplication;
use Symfony\Component\Console\Tester\CommandTester;

describe('bench report', function (): void {
    it('renders markdown reports with comparison data against a snapshot', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-report-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/Benchmarks';

            expect(
                new CommandTester($application->find('snapshot:save'))->execute([
                    'command' => 'snapshot:save',
                    'name' => 'baseline',
                    'path' => $fixturePath,
                ]),
            )->toBe(0);

            $tester = new CommandTester($application->find('report'));
            $statusCode = $tester->execute([
                'command' => 'report',
                'path' => $fixturePath,
                '--format' => 'md',
                '--against' => 'baseline',
            ]);

            expect($statusCode)->toBe(0)
                ->and($tester->getDisplay())->toContain('| Scenario | Subject | Competitor | Parameters | Current Median (ns) | Baseline Median (ns) | Delta % | Winner | Reference Gap | Reference Gain | Significance | Regression |')
                ->and($tester->getDisplay())->toContain('| dto-transform | transform | Bench |')
                ->and($tester->getDisplay())->toContain('| dto-transform | transform | Spatie data |')
                ->and($tester->getDisplay())->toContain('Winner')
                ->and($tester->getDisplay())->toContain('Reference Gap')
                ->and($tester->getDisplay())->toContain('Significance');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('renders csv reports for current runs and baseline comparisons', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-report-csv-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/Benchmarks/TransformBench.php';

            expect(
                new CommandTester($application->find('snapshot:save'))->execute([
                    'command' => 'snapshot:save',
                    'name' => 'baseline',
                    'path' => $fixturePath,
                ]),
            )->toBe(0);

            $reportTester = new CommandTester($application->find('report'));

            expect($reportTester->execute([
                'command' => 'report',
                'path' => $fixturePath,
                '--format' => 'csv',
            ]))->toBe(0)
                ->and($reportTester->getDisplay())->toContain('scenario,subject,competitor,parameter_label')
                ->and($reportTester->getDisplay())->toContain('dto-transform,transform,bench');

            expect($reportTester->execute([
                'command' => 'report',
                'path' => $fixturePath,
                '--format' => 'csv',
                '--against' => 'baseline',
            ]))->toBe(0)
                ->and($reportTester->getDisplay())->toContain('scenario,subject,competitor,parameter_label,current_median,baseline_median,delta_percentage')
                ->and($reportTester->getDisplay())->toContain('dto-transform,transform,bench');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });
});
