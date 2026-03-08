<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Configuration;

use RuntimeException;

use function file_exists;
use function getcwd;
use function is_string;
use function mb_rtrim;
use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class BenchConfigLoader
{
    public static function load(?string $workingDirectory = null): BenchConfig
    {
        $directory = $workingDirectory ?? getcwd();

        if (!is_string($directory) || $directory === '') {
            return BenchConfig::default();
        }

        $path = mb_rtrim($directory, '/').'/bench.php';

        if (!file_exists($path)) {
            return BenchConfig::default();
        }

        $config = require $path;

        throw_unless($config instanceof BenchConfig, RuntimeException::class, 'The bench.php config file must return a BenchConfig instance.');

        /** @var BenchConfig $config */
        return $config;
    }
}
