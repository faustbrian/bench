<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Execution;

use Cline\Bench\Enums\Metric;
use Cline\Bench\Statistics\SummaryStatistics;

use const JSON_THROW_ON_ERROR;

use function array_is_list;
use function array_keys;
use function array_map;
use function is_array;
use function json_encode;
use function ksort;
use function sprintf;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class BenchmarkResult
{
    /**
     * @param list<float>           $samples
     * @param array<string, mixed>  $parameters
     * @param list<string>          $groups
     * @param list<AssertionResult> $assertions
     */
    public function __construct(
        public string $subject,
        public string $scenario,
        public string $competitor,
        public SummaryStatistics $summary,
        public array $samples,
        public array $parameters = [],
        public ?string $caseLabel = null,
        public array $groups = [],
        public array $assertions = [],
        public ?Metric $regressionMetric = null,
        public ?string $regressionTolerance = null,
    ) {}

    public function identifier(): string
    {
        return sprintf(
            '%s::%s::%s::%s',
            $this->scenario,
            $this->subject,
            $this->competitor,
            $this->parameterLabel(),
        );
    }

    public function parameterLabel(): string
    {
        if ($this->caseLabel !== null && $this->caseLabel !== '') {
            return $this->caseLabel;
        }

        if ($this->parameters === []) {
            return 'default';
        }

        return json_encode(self::canonicalizeParameters($this->parameters), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'scenario' => $this->scenario,
            'competitor' => $this->competitor,
            'summary' => $this->summary->toArray(),
            'samples' => $this->samples,
            'parameters' => $this->parameters,
            'case_label' => $this->caseLabel,
            'parameter_label' => $this->parameterLabel(),
            'groups' => $this->groups,
            'assertions' => array_map(
                static fn (AssertionResult $assertion): array => $assertion->toArray(),
                $this->assertions,
            ),
            'regression' => [
                'metric' => $this->regressionMetric?->value,
                'tolerance' => $this->regressionTolerance,
            ],
        ];
    }

    /**
     * @param  array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private static function canonicalizeParameters(array $parameters): array
    {
        $canonical = [];

        foreach (array_keys($parameters) as $key) {
            $value = $parameters[$key];
            $canonical[$key] = is_array($value) ? self::canonicalizeValue($value) : $value;
        }

        ksort($canonical);

        return $canonical;
    }

    /**
     * @param array<mixed> $value
     */
    private static function canonicalizeValue(array $value): mixed
    {
        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => is_array($item) ? self::canonicalizeValue($item) : $item,
                $value,
            );
        }

        /** @var array<string, mixed> $value */
        return self::canonicalizeParameters($value);
    }
}
