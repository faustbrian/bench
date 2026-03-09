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
enum ReportFormat: string
{
    case Table = 'table';
    case Markdown = 'md';
    case Json = 'json';
    case Csv = 'csv';
}
