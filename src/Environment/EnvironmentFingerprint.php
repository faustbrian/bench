<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Environment;

use const PHP_OS_FAMILY;
use const PHP_SAPI;
use const PHP_VERSION;

use function array_filter;
use function array_values;
use function get_loaded_extensions;
use function is_array;
use function is_string;
use function php_uname;
use function sort;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class EnvironmentFingerprint
{
    /**
     * @param list<string> $extensions
     */
    public function __construct(
        public string $phpVersion,
        public string $phpSapi,
        public string $osFamily,
        public string $architecture,
        public array $extensions,
    ) {}

    public static function capture(): self
    {
        /** @var list<string> $extensions */
        $extensions = get_loaded_extensions();
        sort($extensions);

        return new self(
            phpVersion: PHP_VERSION,
            phpSapi: PHP_SAPI,
            osFamily: PHP_OS_FAMILY,
            architecture: php_uname('m'),
            extensions: $extensions,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $rawExtensions = $values['extensions'] ?? [];

        if (!is_array($rawExtensions)) {
            $rawExtensions = [];
        }

        /** @var list<string> $extensions */
        $extensions = array_values(array_filter(
            $rawExtensions,
            is_string(...),
        ));
        sort($extensions);

        return new self(
            phpVersion: is_string($values['php_version'] ?? null) ? $values['php_version'] : '',
            phpSapi: is_string($values['php_sapi'] ?? null) ? $values['php_sapi'] : '',
            osFamily: is_string($values['os_family'] ?? null) ? $values['os_family'] : '',
            architecture: is_string($values['architecture'] ?? null) ? $values['architecture'] : '',
            extensions: $extensions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'php_version' => $this->phpVersion,
            'php_sapi' => $this->phpSapi,
            'os_family' => $this->osFamily,
            'architecture' => $this->architecture,
            'extensions' => $this->extensions,
        ];
    }

    /**
     * @return list<string>
     */
    public function mismatches(self $baseline): array
    {
        $mismatches = [];

        if ($this->phpVersion !== $baseline->phpVersion) {
            $mismatches[] = 'php_version';
        }

        if ($this->phpSapi !== $baseline->phpSapi) {
            $mismatches[] = 'php_sapi';
        }

        if ($this->osFamily !== $baseline->osFamily) {
            $mismatches[] = 'os_family';
        }

        if ($this->architecture !== $baseline->architecture) {
            $mismatches[] = 'architecture';
        }

        if ($this->extensions !== $baseline->extensions) {
            $mismatches[] = 'extensions';
        }

        return $mismatches;
    }
}
