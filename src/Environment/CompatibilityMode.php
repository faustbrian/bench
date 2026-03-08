<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Environment;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum CompatibilityMode: string
{
    case Ignore = 'ignore';
    case Warn = 'warn';
    case Fail = 'fail';
}
