<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Console;

use Cline\Bench\Console\Commands\CompareCommand;
use Cline\Bench\Console\Commands\InitCommand;
use Cline\Bench\Console\Commands\ReportCommand;
use Cline\Bench\Console\Commands\RunCommand;
use Cline\Bench\Console\Commands\SnapshotAssertCommand;
use Cline\Bench\Console\Commands\SnapshotSaveCommand;
use Override;
use Symfony\Component\Console\Application;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class BenchApplication extends Application
{
    public function __construct()
    {
        parent::__construct('bench');

        $this->addCommands([
            new RunCommand(),
            new InitCommand(),
            new ReportCommand(),
            new CompareCommand(),
            new SnapshotSaveCommand(),
            new SnapshotAssertCommand(),
        ]);
    }

    #[Override()]
    protected function getDefaultCommands(): array
    {
        return parent::getDefaultCommands();
    }
}
