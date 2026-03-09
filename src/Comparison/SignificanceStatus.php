<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Comparison;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum SignificanceStatus: string
{
    case Winner = 'winner';
    case Significant = 'significant';
    case NotSignificant = 'not_significant';
    case Disabled = 'disabled';
    case NotAvailable = 'not_available';
}
