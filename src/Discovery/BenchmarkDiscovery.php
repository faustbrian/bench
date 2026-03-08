<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Bench\Discovery;

use Cline\Bench\Attributes\After;
use Cline\Bench\Attributes\Assert;
use Cline\Bench\Attributes\Before;
use Cline\Bench\Attributes\Bench;
use Cline\Bench\Attributes\Competitor;
use Cline\Bench\Attributes\Group;
use Cline\Bench\Attributes\Iterations;
use Cline\Bench\Attributes\Params;
use Cline\Bench\Attributes\Regression;
use Cline\Bench\Attributes\Revs;
use Cline\Bench\Attributes\Scenario;
use Cline\Bench\Attributes\Warmup;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

use function basename;
use function dirname;
use function get_declared_classes;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function mb_strtolower;
use function preg_replace;
use function realpath;
use function str_starts_with;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class BenchmarkDiscovery
{
    /**
     * @return list<DiscoveredBenchmark>
     */
    public function discover(
        string $path,
        int $defaultIterations = 5,
        int $defaultRevolutions = 1,
        int $defaultWarmupIterations = 0,
    ): array {
        $resolvedPath = realpath($path);

        if ($resolvedPath === false) {
            return [];
        }

        $finder = new Finder();

        if (is_file($resolvedPath)) {
            $finder->files()->in(dirname($resolvedPath))->name(basename($resolvedPath));
        } else {
            $finder->files()->in($resolvedPath)->name('*.php');
        }

        foreach ($finder as $file) {
            require_once $file->getRealPath();
        }

        $benchmarks = [];

        foreach (get_declared_classes() as $className) {
            $reflectionClass = new ReflectionClass($className);
            $fileName = $reflectionClass->getFileName();

            if ($fileName === false) {
                continue;
            }

            if (!str_starts_with($fileName, $resolvedPath)) {
                continue;
            }

            $benchmarks = [...$benchmarks, ...$this->discoverClass(
                $reflectionClass,
                $defaultIterations,
                $defaultRevolutions,
                $defaultWarmupIterations,
            )];
        }

        return $benchmarks;
    }

    /**
     * @param  ReflectionClass<object>   $reflectionClass
     * @return list<DiscoveredBenchmark>
     */
    private function discoverClass(
        ReflectionClass $reflectionClass,
        int $defaultIterations,
        int $defaultRevolutions,
        int $defaultWarmupIterations,
    ): array {
        $scenario = $this->stringAttribute($reflectionClass, Scenario::class, 'name', $reflectionClass->getShortName());
        $competitor = $this->stringAttribute($reflectionClass, Competitor::class, 'name', $reflectionClass->getShortName());
        $classIterations = $this->intAttribute($reflectionClass, Iterations::class, 'count', $defaultIterations);
        $classRevolutions = $this->intAttribute($reflectionClass, Revs::class, 'count', $defaultRevolutions);
        $classWarmup = $this->intAttribute($reflectionClass, Warmup::class, 'count', $defaultWarmupIterations);
        $classBefore = $this->attributeList($reflectionClass, Before::class);
        $classAfter = $this->attributeList($reflectionClass, After::class);
        $classGroups = $this->groupAttributes($reflectionClass);

        $benchmarks = [];

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $benchAttribute = $method->getAttributes(Bench::class)[0] ?? null;

            if ($benchAttribute === null) {
                continue;
            }

            $bench = $benchAttribute->newInstance();
            $subject = $bench->name ?? preg_replace('/^bench/', '', $method->getName()) ?: $method->getName();
            $subject = mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $subject));

            $benchmarks[] = new DiscoveredBenchmark(
                sourcePath: (string) $reflectionClass->getFileName(),
                className: $reflectionClass->getName(),
                methodName: $method->getName(),
                subject: $subject,
                scenario: $scenario,
                competitor: $competitor,
                iterations: $this->intAttribute($method, Iterations::class, 'count', $classIterations),
                revolutions: $this->intAttribute($method, Revs::class, 'count', $classRevolutions),
                warmupIterations: $this->intAttribute($method, Warmup::class, 'count', $classWarmup),
                beforeMethods: [...$classBefore, ...$this->attributeList($method, Before::class)],
                afterMethods: [...$classAfter, ...$this->attributeList($method, After::class)],
                groups: [...$classGroups, ...$this->groupAttributes($method)],
                parameterSets: $this->parameterSets($method),
                assertions: $this->assertions($method),
                regressionMetric: $this->stringAttribute($method, Regression::class, 'metric', ''),
                regressionTolerance: $this->stringAttribute($method, Regression::class, 'tolerance', ''),
            );
        }

        return $benchmarks;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     */
    private function stringAttribute(
        ReflectionClass|ReflectionMethod $reflection,
        string $attribute,
        string $property,
        string $default,
    ): string {
        $value = $this->attributeValue($reflection, $attribute, $property);

        return is_string($value) ? $value : $default;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     */
    private function intAttribute(
        ReflectionClass|ReflectionMethod $reflection,
        string $attribute,
        string $property,
        int $default,
    ): int {
        $value = $this->attributeValue($reflection, $attribute, $property);

        return is_int($value) ? $value : $default;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     */
    private function attributeValue(ReflectionClass|ReflectionMethod $reflection, string $attribute, string $property): mixed
    {
        $attributes = $reflection->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance()->{$property};
    }

    /**
     * @param  ReflectionClass<object>|ReflectionMethod $reflection
     * @return list<string>
     */
    private function attributeList(ReflectionClass|ReflectionMethod $reflection, string $attribute): array
    {
        $values = [];

        foreach ($reflection->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF) as $foundAttribute) {
            $instance = $foundAttribute->newInstance();
            $methods = [];

            if ($instance instanceof Before || $instance instanceof After) {
                $methods = is_array($instance->methods) ? $instance->methods : [$instance->methods];
            }

            foreach ($methods as $method) {
                $values[] = $method;
            }
        }

        return $values;
    }

    /**
     * @param  ReflectionClass<object>|ReflectionMethod $reflection
     * @return list<string>
     */
    private function groupAttributes(ReflectionClass|ReflectionMethod $reflection): array
    {
        $groups = [];

        foreach ($reflection->getAttributes(Group::class, ReflectionAttribute::IS_INSTANCEOF) as $foundAttribute) {
            $instance = $foundAttribute->newInstance();
            $names = is_array($instance->names) ? $instance->names : [$instance->names];

            foreach ($names as $name) {
                $groups[] = $name;
            }
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parameterSets(ReflectionMethod $method): array
    {
        $attribute = $method->getAttributes(Params::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        if ($attribute === null) {
            return [];
        }

        $instance = $attribute->newInstance();
        $sets = [];

        foreach ($instance->sets as $set) {
            /** @var array<string, mixed> $set */
            $sets[] = $set;
        }

        return $sets;
    }

    /**
     * @return list<BenchmarkAssertion>
     */
    private function assertions(ReflectionMethod $method): array
    {
        $assertions = [];

        foreach ($method->getAttributes(Assert::class, ReflectionAttribute::IS_INSTANCEOF) as $foundAttribute) {
            $instance = $foundAttribute->newInstance();

            $assertions[] = new BenchmarkAssertion(
                metric: $instance->metric,
                operator: $instance->operator,
                value: (float) $instance->value,
            );
        }

        return $assertions;
    }
}
