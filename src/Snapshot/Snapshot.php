<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Snapshot;

use Cline\Bench\Execution\BenchmarkResult;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Snapshot
{
    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    public function __construct(
        public string $name,
        public array $results,
        public array $metadata,
    ) {}
}
