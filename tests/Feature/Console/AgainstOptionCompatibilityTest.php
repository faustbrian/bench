<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Console\BenchApplication;
use Symfony\Component\Console\Tester\CommandTester;

describe('against option compatibility', function (): void {
    it('supports compare via the --against option from the written v1 workflow', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-against-option-'.bin2hex(random_bytes(8));
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

            $tester = new CommandTester($application->find('compare'));

            expect($tester->execute([
                'command' => 'compare',
                '--against' => 'snapshot:latest',
                'path' => $fixturePath,
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('Baseline')
                ->and($tester->getDisplay())->toContain('Winner');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });

    it('supports snapshot:assert via the --against option from the written v1 workflow', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-against-assert-'.bin2hex(random_bytes(8));
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

            $tester = new CommandTester($application->find('snapshot:assert'));

            expect($tester->execute([
                'command' => 'snapshot:assert',
                '--against' => 'baseline',
                'path' => $fixturePath,
                '--tolerance' => '10000%',
            ]))->toBe(0)
                ->and($tester->getDisplay())->toContain('tolerance 10000%');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });
});
