<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Storage;

use Cline\Bench\Execution\BenchmarkResult;
use Cline\Bench\Snapshot\Snapshot;
use Cline\Bench\Snapshot\SnapshotStore;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class RunStore
{
    public function __construct(
        private SnapshotStore $store,
    ) {}

    /**
     * @param list<BenchmarkResult> $results
     * @param array<string, mixed>  $metadata
     */
    public function save(string $name, array $results, array $metadata = []): void
    {
        $this->store->save($name, $results, $metadata);
    }

    public function load(string $name): Snapshot
    {
        return $this->store->load($name);
    }
}
