<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Storage;

use Cline\Bench\Snapshot\Snapshot;

/**
 * @author Brian Faust <brian@faust.software>
 * @psalm-immutable
 */
final readonly class ScenarioReferenceResolver
{
    public function __construct(
        private ReferenceResolver $resolver,
    ) {}

    /**
     * @param list<string>          $scenarios
     * @param array<string, string> $scenarioReferences
     */
    public function resolve(array $scenarios, array $scenarioReferences): Snapshot
    {
        $results = [];
        $metadata = [
            'scenario_references' => [],
        ];

        foreach ($scenarios as $scenario) {
            $reference = $scenarioReferences[$scenario] ?? null;

            if ($reference === null) {
                continue;
            }

            $snapshot = $this->resolver->resolve($reference);
            $metadata['scenario_references'][$scenario] = $reference;

            foreach ($snapshot->results as $result) {
                if ($result->scenario !== $scenario) {
                    continue;
                }

                $results[] = $result;
            }
        }

        return new Snapshot(
            name: 'pinned',
            results: $results,
            metadata: $metadata,
        );
    }
}
