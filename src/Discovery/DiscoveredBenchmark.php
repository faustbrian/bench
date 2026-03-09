<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Discovery;

use Cline\Bench\Enums\Metric;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DiscoveredBenchmark
{
    /**
     * @param list<string>               $beforeMethods
     * @param list<string>               $afterMethods
     * @param list<string>               $groups
     * @param list<array<string, mixed>> $parameterSets
     * @param list<BenchmarkAssertion>   $assertions
     */
    public function __construct(
        public string $sourcePath,
        public string $className,
        public string $methodName,
        public string $subject,
        public string $scenario,
        public string $competitor,
        public int $iterations,
        public int $revolutions,
        public int $warmupIterations,
        public array $beforeMethods = [],
        public array $afterMethods = [],
        public array $groups = [],
        public array $parameterSets = [],
        public array $assertions = [],
        public ?Metric $regressionMetric = null,
        public ?string $regressionTolerance = null,
    ) {}
}
