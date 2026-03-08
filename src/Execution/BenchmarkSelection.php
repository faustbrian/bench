<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Execution;

use Cline\Bench\Discovery\DiscoveredBenchmark;

use const JSON_THROW_ON_ERROR;

use function array_intersect;
use function array_map;
use function implode;
use function in_array;
use function json_encode;
use function mb_strtolower;
use function str_contains;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class BenchmarkSelection
{
    /**
     * @param list<string> $groups
     * @param list<string> $competitors
     */
    public function __construct(
        public ?string $filter = null,
        public array $groups = [],
        public array $competitors = [],
    ) {}

    public function matchesDiscoveredBenchmark(DiscoveredBenchmark $benchmark): bool
    {
        if ($this->competitors !== [] && !in_array($benchmark->competitor, $this->competitors, true)) {
            return false;
        }

        if ($this->groups !== [] && array_intersect($benchmark->groups, $this->groups) === []) {
            return false;
        }

        if ($this->filter === null || $this->filter === '') {
            return true;
        }

        $needle = mb_strtolower($this->filter);
        $haystack = mb_strtolower(implode(' ', [
            $benchmark->scenario,
            $benchmark->subject,
            $benchmark->competitor,
            $benchmark->className,
            $benchmark->methodName,
            json_encode($benchmark->parameterSets, JSON_THROW_ON_ERROR),
        ]));

        return str_contains($haystack, $needle);
    }

    /**
     * @return array{filter: ?string, groups: list<string>, competitors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter,
            'groups' => array_map(static fn (string $group): string => $group, $this->groups),
            'competitors' => array_map(static fn (string $competitor): string => $competitor, $this->competitors),
        ];
    }
}
