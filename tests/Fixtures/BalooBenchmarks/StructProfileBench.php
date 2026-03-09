<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\BalooBenchmarks;

use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Group;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Regression;
use Cline\Bench\Attributes\Revolutions;
use Cline\Bench\Attributes\Scenario;
use Cline\Bench\Enums\Metric;
use Tests\Fixtures\BalooBenchmarks\Support\BalooBenchCase;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('baloo-profile')]
#[Competitor('struct')]
#[Group(['baloo', 'dto', 'comparison'])]
final class StructProfileBench extends BalooBenchCase
{
    #[Bench('profile-collection-transformation')]
    #[Iterations(3)]
    #[Revolutions(120)]
    #[Regression(metric: Metric::Median, tolerance: '5%')]
    public function benchProfileCollectionTransformation(): void
    {
        $this->structTransform(profile: true, cached: true);
    }

    #[Bench('profile-object-transformation')]
    #[Iterations(3)]
    #[Revolutions(240)]
    public function benchProfileObjectTransformation(): void
    {
        $this->structTransform(profile: true, cached: true);
    }

    #[Bench('profile-collection-creation')]
    #[Iterations(3)]
    #[Revolutions(120)]
    public function benchProfileCollectionCreation(): void
    {
        $this->structCreate(profile: true, cached: true);
    }

    #[Bench('profile-object-creation')]
    #[Iterations(3)]
    #[Revolutions(240)]
    public function benchProfileObjectCreation(): void
    {
        $this->structCreate(profile: true, cached: true);
    }
}
