<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Configuration\BenchConfig;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Execution\BenchmarkRunner;
use Tests\Fixtures\Benchmarks\CalibratedBench;
use Tests\Fixtures\Benchmarks\CaseLabeledBench;
use Tests\Fixtures\Benchmarks\DefaultConfiguredHookedBench;
use Tests\Fixtures\Benchmarks\HookedBench;
use Tests\Fixtures\Benchmarks\IsolatedBench;
use Tests\Fixtures\Benchmarks\IterationIsolatedBench;
use Tests\Fixtures\Benchmarks\ParameterizedBench;

describe('BenchmarkRunner', function (): void {
    it('executes warmups and before/after hooks around benchmark iterations', function (): void {
        HookedBench::reset();

        $results = new BenchmarkRunner()->runPath(__DIR__.'/../../Fixtures/Benchmarks');
        $hookedResult = null;

        foreach ($results as $result) {
            if ($result->competitor !== 'hooked') {
                continue;
            }

            $hookedResult = $result;

            break;
        }

        expect($hookedResult)->toBeInstanceOf(BenchmarkResult::class);

        /** @var BenchmarkResult $hookedResult */
        expect($hookedResult->summary->samples)->toBe(2)
            ->and(HookedBench::$beforeCalls)->toBe(3)
            ->and(HookedBench::$afterCalls)->toBe(3)
            ->and(HookedBench::$subjectCalls)->toBe(6);
    });

    it('expands parameter sets and records benchmark metadata on results', function (): void {
        ParameterizedBench::reset();

        $results = new BenchmarkRunner()->runPath(__DIR__.'/../../Fixtures/Benchmarks');
        $parameterizedResults = array_values(array_filter(
            $results,
            static fn (BenchmarkResult $result): bool => $result->competitor === 'bench' && $result->subject === 'transform-payload',
        ));

        expect($parameterizedResults)->toHaveCount(2)
            ->and($parameterizedResults[0]->groups)->toBe(['dto', 'comparison'])
            ->and($parameterizedResults[0]->assertions)->toHaveCount(1)
            ->and($parameterizedResults[0]->assertions[0]->passed)->toBeTrue()
            ->and($parameterizedResults[0]->regressionMetric)->toBe(Metric::Median)
            ->and($parameterizedResults[0]->regressionTolerance)->toBe('7%')
            ->and($parameterizedResults[0]->parameters)->toBe(['size' => 'small', 'multiplier' => 10])
            ->and($parameterizedResults[1]->parameters)->toBe(['size' => 'large', 'multiplier' => 100])
            ->and(ParameterizedBench::$sizes)->toBe(['small', 'large']);
    });

    it('uses explicit case labels without passing them into benchmark arguments', function (): void {
        CaseLabeledBench::reset();

        $results = new BenchmarkRunner()->runPath(__DIR__.'/../../Fixtures/Benchmarks/CaseLabeledBench.php');

        expect($results)->toHaveCount(2)
            ->and($results[0]->parameterLabel())->toBe('small-payload')
            ->and($results[0]->parameters)->toBe(['size' => 'small'])
            ->and($results[1]->parameterLabel())->toBe('large-payload')
            ->and($results[1]->parameters)->toBe(['size' => 'large'])
            ->and(CaseLabeledBench::$sizes)->toBe(['small', 'large']);
    });

    it('uses config defaults for iterations revs and warmup', function (): void {
        DefaultConfiguredHookedBench::reset();

        $results = new BenchmarkRunner()->runPath(
            __DIR__.'/../../Fixtures/Benchmarks/DefaultConfiguredHookedBench.php',
            BenchConfig::default()
                ->withDefaultIterations(4)
                ->withDefaultRevolutions(2)
                ->withDefaultWarmupIterations(1),
        );

        expect($results)->toHaveCount(1)
            ->and($results[0]->summary->samples)->toBe(4)
            ->and(DefaultConfiguredHookedBench::$beforeCalls)->toBe(5)
            ->and(DefaultConfiguredHookedBench::$afterCalls)->toBe(5)
            ->and(DefaultConfiguredHookedBench::$subjectCalls)->toBe(10);
    });

    it('calibrates revolutions upward to hit a target runtime budget', function (): void {
        CalibratedBench::reset();

        $results = new BenchmarkRunner()->runPath(
            __DIR__.'/../../Fixtures/Benchmarks/CalibratedBench.php',
            BenchConfig::default()
                ->withDefaultIterations(2)
                ->withDefaultRevolutions(1)
                ->withCalibrationBudgetNanoseconds(5_000_000),
        );

        expect($results)->toHaveCount(1)
            ->and($results[0]->summary->samples)->toBe(2)
            ->and(CalibratedBench::$subjectCalls)->toBeGreaterThan(2);
    });

    it('can isolate benchmark execution into a child process', function (): void {
        IsolatedBench::reset();

        $results = new BenchmarkRunner()->runPath(
            __DIR__.'/../../Fixtures/Benchmarks/IsolatedBench.php',
            BenchConfig::default()
                ->withDefaultIterations(2)
                ->withProcessIsolation(true),
        );

        expect($results)->toHaveCount(1)
            ->and($results[0]->summary->samples)->toBe(2)
            ->and(IsolatedBench::$parentProcessCalls)->toBe(0);
    });

    it('creates a fresh benchmark instance for each warmup and measured iteration', function (): void {
        IterationIsolatedBench::reset();

        $results = new BenchmarkRunner()->runPath(
            __DIR__.'/../../Fixtures/Benchmarks/IterationIsolatedBench.php',
            BenchConfig::default()
                ->withDefaultWarmupIterations(1),
        );

        expect($results)->toHaveCount(1)
            ->and($results[0]->summary->samples)->toBe(3)
            ->and(IterationIsolatedBench::$instances)->toBe(4)
            ->and(IterationIsolatedBench::$runs)->toBe(4);
    });
});
