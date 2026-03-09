<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Enums;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum TimeUnit: string
{
    case Nanoseconds = 'ns';
    case Microseconds = 'μs';
    case Milliseconds = 'ms';
    case Seconds = 's';
}
