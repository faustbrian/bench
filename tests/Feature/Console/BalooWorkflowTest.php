<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Console\BenchApplication;
use Symfony\Component\Console\Tester\CommandTester;

describe('baloo-shaped workflow', function (): void {
    it('runs a comparison-first dto suite without a custom XML post-processor', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-baloo-workflow-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory, 0o755, true);
        $previousDirectory = getcwd();

        chdir($workingDirectory);

        try {
            $application = new BenchApplication();
            $fixturePath = __DIR__.'/../../Fixtures/BalooBenchmarks';

            $snapshotTester = new CommandTester($application->find('snapshot:save'));
            $compareTester = new CommandTester($application->find('compare'));
            $runTester = new CommandTester($application->find('run'));

            expect($snapshotTester->execute([
                'command' => 'snapshot:save',
                'name' => 'baloo-baseline',
                'path' => $fixturePath,
            ]))->toBe(0);

            expect($runTester->execute([
                'command' => 'run',
                'path' => $fixturePath,
                '--format' => 'json',
            ]))->toBe(0);

            /**
             * @var array{
             *     results: list<array<string, mixed>>,
             *     comparison: array{
             *         rows: list<array<string, mixed>>,
             *         geometric_mean_reference_gap: float
             *     }
             * } $runPayload
             */
            $runPayload = json_decode($runTester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

            expect($runPayload['results'])->toHaveCount(24)
                ->and($runPayload['comparison']['rows'])->toHaveCount(24)
                ->and($runPayload['comparison'])->toHaveKey('geometric_mean_reference_gap')
                ->and($runPayload['results'][0])->toHaveKey('groups');

            expect($compareTester->execute([
                'command' => 'compare',
                '--against' => 'snapshot:latest',
                'path' => $fixturePath,
                '--format' => 'md',
            ]))->toBe(0)
                ->and($compareTester->getDisplay())->toContain('| baloo-data | collection-transformation | Struct |')
                ->and($compareTester->getDisplay())->toContain('| baloo-profile | profile-object-creation | Spatie |')
                ->and($compareTester->getDisplay())->toContain('Winner')
                ->and($compareTester->getDisplay())->toContain('Significance');
        } finally {
            if ($previousDirectory !== false) {
                chdir($previousDirectory);
            }
        }
    });
});
