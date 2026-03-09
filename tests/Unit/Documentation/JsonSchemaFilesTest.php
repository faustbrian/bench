<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

describe('JSON schema files', function (): void {
    it('provides valid machine-readable schemas for run and comparison reports', function (): void {
        /** @var array<string, mixed> $runSchema */
        $runSchema = json_decode((string) file_get_contents(__DIR__.'/../../../docs/json-schema/run-v1.schema.json'), true, flags: \JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $comparisonSchema */
        $comparisonSchema = json_decode((string) file_get_contents(__DIR__.'/../../../docs/json-schema/comparison-v1.schema.json'), true, flags: \JSON_THROW_ON_ERROR);

        expect($runSchema)
            ->toBeArray()
            ->and($runSchema)->toHaveKey('$schema', 'https://json-schema.org/draft/2020-12/schema')
            ->and($runSchema)->toHaveKey('$id', 'https://cline.sh/bench/schema/run-v1.schema.json')
            ->and($runSchema)->toHaveKey('title', 'bench run report v1')
            ->and($runSchema)->toHaveKey('required')
            ->and($runSchema['required'])->toBe(['results', 'comparison', 'metadata'])
            ->and($runSchema)->toHaveKey('$defs.benchmarkResult')
            ->and($runSchema)->toHaveKey('$defs.runComparisonRow');

        expect($comparisonSchema)
            ->toBeArray()
            ->and($comparisonSchema)->toHaveKey('$schema', 'https://json-schema.org/draft/2020-12/schema')
            ->and($comparisonSchema)->toHaveKey('$id', 'https://cline.sh/bench/schema/comparison-v1.schema.json')
            ->and($comparisonSchema)->toHaveKey('title', 'bench comparison report v1')
            ->and($comparisonSchema)->toHaveKey('required')
            ->and($comparisonSchema['required'])->toBe(['results', 'comparisons', 'reference', 'metadata'])
            ->and($comparisonSchema)->toHaveKey('$defs.benchmarkResult')
            ->and($comparisonSchema)->toHaveKey('$defs.referenceComparisonRow');
    });
});
