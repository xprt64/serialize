<?php
/** Copyright (c) 2026 Constantin Galbenu <gica.galbenu@gmail.com>. All rights reserved. */

namespace Gica\Serialize\ObjectHydrator\Compiled;

/** Finds the generated hydrator of a class: a function `(ObjectHydrator $hydrator, object $emptyObject, $document): void` that sets the properties of the object. */
interface CompiledHydrators
{
    public function hydratorFor(string $class): ?\Closure;
}
