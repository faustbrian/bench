<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Bench\Attributes\After;
use Cline\Bench\Attributes\Before;
use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Group;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Params;
use Cline\Bench\Attributes\Regression;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Scenario;
use Cline\Bench\Attributes\Threshold;
use Cline\Bench\Attributes\Warmup;
use Cline\Bench\Enums\Metric;
use Cline\Bench\Enums\ThresholdOperator;

describe('Attributes', function (): void {
    it('stores constructor arguments for benchmark metadata', function (): void {
        expect(
            new Bench('transform')->name,
        )->toBe('transform');
        expect(
            new Competitor('bench')->name,
        )->toBe('bench');
        expect(
            new Scenario('dto-transform')->name,
        )->toBe('dto-transform');
        expect(
            new Iterations(5)->count,
        )->toBe(5);
        expect(
            new Revolutions(10)->count,
        )->toBe(10);
        expect(
            new Warmup(2)->count,
        )->toBe(2);
        expect(
            new Before(['boot'])->methods,
        )->toBe(['boot']);
        expect(
            new After(['teardown'])->methods,
        )->toBe(['teardown']);
        expect(
            new Regression()->metric,
        )->toBe(Metric::Median);
        expect(
            new Regression()->tolerance,
        )->toBe('5%');
        expect(
            new Group(['dto', 'comparison'])->names,
        )->toBe(['dto', 'comparison']);
        expect(
            new Params([['size' => 'small']])->sets,
        )->toBe([['size' => 'small']]);

        $assert = new Threshold(Metric::Median, ThresholdOperator::LessThan, 1_000_000.0);

        expect($assert->metric)->toBe(Metric::Median);
        expect($assert->operator)->toBe(ThresholdOperator::LessThan);
        expect($assert->value)->toBe(1_000_000.0);
    });
});
