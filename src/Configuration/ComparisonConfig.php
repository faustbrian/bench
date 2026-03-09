<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Configuration;

use Cline\Bench\Enums\ComparisonReference;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ComparisonConfig
{
    /**
     * @param list<string>          $preferredCompetitors
     * @param array<string, string> $competitorAliases
     * @param array<string, string> $scenarioReferences
     */
    public function __construct(
        public array $preferredCompetitors,
        public array $competitorAliases,
        public array $scenarioReferences,
        public ComparisonReference $comparisonReference,
        public bool $significanceEnabled,
        public float $significanceAlpha,
        public int $significanceMinimumSamples,
    ) {}
}
