<?php

declare(strict_types=1);

namespace Laratesto\Rector\Analysis;

use Laratesto\Rector\Configuration\ConfiguredHierarchy;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;

/** Keep upstream structural rewrites out of the Laravel conversion frontier. */
final class LaravelClassBoundary
{
    public function __construct(
        private readonly ConfiguredHierarchy $hierarchy,
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function owns(Class_ $class): bool
    {
        if ($this->hierarchy->recognizesTestClass($class)) {
            return true;
        }

        $name = $class->namespacedName?->toString();
        if ($name === null || !$this->reflectionProvider->hasClass($name)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($name);
        // Include Laravel classes outside a custom base_classes list: leaving
        // those unchanged is part of that configuration's contract.
        foreach (['Illuminate\\Foundation\\Testing\\TestCase', ...$this->hierarchy->sourceBases()] as $base) {
            if ($reflection->getName() === $base || $reflection->isSubclassOf($base)) {
                return true;
            }
        }

        return false;
    }
}
