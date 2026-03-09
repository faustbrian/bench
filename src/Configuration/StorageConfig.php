<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Configuration;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class StorageConfig
{
    public function __construct(
        public string $benchmarkPath,
        public string $snapshotPath,
        public string $runPath,
        public ?string $bootstrapPath,
    ) {}
}
