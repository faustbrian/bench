<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\BalooBenchmarks\Support;

use function range;

/**
 * @author Brian Faust <brian@cline.sh>
 */
abstract class BalooBenchCase
{
    /**
     * @return list<int>
     */
    final protected function payload(bool $profile): array
    {
        $size = $profile ? 60 : 180;

        return range(1, $size);
    }

    final protected function structTransform(bool $profile, bool $cached): int
    {
        return $this->iterate(
            $this->payload($profile),
            $cached ? 18 : 26,
            2,
        );
    }

    final protected function spatieTransform(bool $profile, bool $cached): int
    {
        return $this->iterate(
            $this->payload($profile),
            $cached ? 22 : 31,
            3,
        );
    }

    final protected function structCreate(bool $profile, bool $cached): int
    {
        return $this->iterate(
            $this->payload($profile),
            $cached ? 20 : 29,
            4,
        );
    }

    final protected function spatieCreate(bool $profile, bool $cached): int
    {
        return $this->iterate(
            $this->payload($profile),
            $cached ? 25 : 34,
            5,
        );
    }

    /**
     * @param list<int> $values
     */
    private function iterate(array $values, int $multiplier, int $offset): int
    {
        $sum = 0;

        foreach ($values as $value) {
            $sum += (($value + $offset) * $multiplier) % 97;
        }

        return $sum;
    }
}
