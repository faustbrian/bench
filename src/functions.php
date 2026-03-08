<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!function_exists('throw_if')) {
    /**
     * @param class-string<Throwable>|Throwable $exception
     */
    function throw_if(bool $condition, string|Throwable $exception, string $message = ''): void
    {
        if (!$condition) {
            return;
        }

        $throwable = match (true) {
            $exception instanceof Throwable => $exception,
            $message !== '' => new $exception($message),
            default => new $exception(),
        };

        throw $throwable;
    }
}

if (!function_exists('throw_unless')) {
    /**
     * @param class-string<Throwable>|Throwable $exception
     */
    function throw_unless(bool $condition, string|Throwable $exception, string $message = ''): void
    {
        throw_if(!$condition, $exception, $message);
    }
}
