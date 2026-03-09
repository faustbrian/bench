<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Storage;

use Cline\Bench\Snapshot\Snapshot;
use Cline\Bench\Snapshot\SnapshotStore;
use RuntimeException;

use function mb_substr;
use function str_starts_with;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ReferenceResolver
{
    public function __construct(
        private string $snapshotPath,
        private string $runPath,
    ) {}

    public function resolve(string $reference): Snapshot
    {
        if (str_starts_with($reference, 'snapshot:')) {
            return new SnapshotStore($this->snapshotPath)->load(mb_substr($reference, 9));
        }

        if (str_starts_with($reference, 'run:')) {
            return new RunStore(
                new SnapshotStore($this->runPath),
            )->load(mb_substr($reference, 4));
        }

        $snapshotStore = new SnapshotStore($this->snapshotPath);

        try {
            return $snapshotStore->load($reference);
        } catch (RuntimeException) {
            return new RunStore(
                new SnapshotStore($this->runPath),
            )->load($reference);
        }
    }
}
