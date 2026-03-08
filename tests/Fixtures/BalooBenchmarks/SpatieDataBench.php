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
use Cline\Bench\Attributes\Revs;
use Cline\Bench\Attributes\Scenario;
use Tests\Fixtures\BalooBenchmarks\Support\BalooBenchCase;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Scenario('baloo-data')]
#[Competitor('spatie')]
#[Group(['baloo', 'dto', 'comparison'])]
final class SpatieDataBench extends BalooBenchCase
{
    #[Bench('collection-transformation')]
    #[Iterations(3)]
    #[Revs(120)]
    #[Regression(metric: 'median', tolerance: '5%')]
    public function benchCollectionTransformation(): void
    {
        $this->spatieTransform(profile: false, cached: true);
    }

    #[Bench('object-transformation')]
    #[Iterations(3)]
    #[Revs(240)]
    public function benchObjectTransformation(): void
    {
        $this->spatieTransform(profile: false, cached: true);
    }

    #[Bench('collection-creation')]
    #[Iterations(3)]
    #[Revs(120)]
    public function benchCollectionCreation(): void
    {
        $this->spatieCreate(profile: false, cached: true);
    }

    #[Bench('object-creation')]
    #[Iterations(3)]
    #[Revs(240)]
    public function benchObjectCreation(): void
    {
        $this->spatieCreate(profile: false, cached: true);
    }

    #[Bench('collection-transformation-without-cache')]
    #[Iterations(3)]
    #[Revs(120)]
    public function benchCollectionTransformationWithoutCache(): void
    {
        $this->spatieTransform(profile: false, cached: false);
    }

    #[Bench('object-transformation-without-cache')]
    #[Iterations(3)]
    #[Revs(240)]
    public function benchObjectTransformationWithoutCache(): void
    {
        $this->spatieTransform(profile: false, cached: false);
    }

    #[Bench('collection-creation-without-cache')]
    #[Iterations(3)]
    #[Revs(120)]
    public function benchCollectionCreationWithoutCache(): void
    {
        $this->spatieCreate(profile: false, cached: false);
    }

    #[Bench('object-creation-without-cache')]
    #[Iterations(3)]
    #[Revs(240)]
    public function benchObjectCreationWithoutCache(): void
    {
        $this->spatieCreate(profile: false, cached: false);
    }
}
