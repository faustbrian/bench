<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

describe('bench binary entrypoint', function (): void {
    it('boots correctly from an installed vendor package path', function (): void {
        $workingDirectory = sys_get_temp_dir().'/bench-bin-'.bin2hex(random_bytes(8));
        $projectDirectory = $workingDirectory.'/project';
        $packageDirectory = $projectDirectory.'/vendor/cline/bench';
        $binaryPath = $packageDirectory.'/bin/bench';
        $autoloadPath = $projectDirectory.'/vendor/autoload.php';

        mkdir($packageDirectory.'/bin', 0o755, true);
        copy(__DIR__.'/../../../bin/bench', $binaryPath);

        file_put_contents($autoloadPath, sprintf(
            "<?php declare(strict_types=1);\n\nrequire %s;\n",
            var_export(__DIR__.'/../../../vendor/autoload.php', true),
        ));

        chmod($binaryPath, 0o755);

        $output = [];
        $exitCode = 1;

        exec(sprintf('php %s list --raw 2>&1', escapeshellarg($binaryPath)), $output, $exitCode);

        expect($exitCode)->toBe(0)
            ->and(implode("\n", $output))->toContain('run');
    });
});
