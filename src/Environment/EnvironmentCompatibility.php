<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Environment;

use function implode;
use function is_array;
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class EnvironmentCompatibility
{
    /**
     * @param array<string, mixed> $snapshotMetadata
     */
    public function assess(array $snapshotMetadata): ?string
    {
        /** @var array<string, mixed> $environment */
        $environment = is_array($snapshotMetadata['environment'] ?? null)
            ? $snapshotMetadata['environment']
            : [];

        $baseline = EnvironmentFingerprint::fromArray(
            $environment,
        );

        $current = EnvironmentFingerprint::capture();
        $mismatches = $current->mismatches($baseline);

        if ($mismatches === []) {
            return null;
        }

        return sprintf(
            'environment mismatch: %s',
            implode(', ', $mismatches),
        );
    }
}
