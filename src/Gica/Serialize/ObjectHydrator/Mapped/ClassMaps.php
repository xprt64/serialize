<?php
/** Copyright (c) 2026 Constantin Galbenu <gica.galbenu@gmail.com>. All rights reserved. */

namespace Gica\Serialize\ObjectHydrator\Mapped;

/**
 * The hydration map of a class, written ahead of time (ClassMapBuilder): property name => how to build its value. With it ObjectHydrator needs no reflection.
 * Entry keys (all optional): `type` builtin type to cast to (string, int, float, bool, mixed, null); `class` class of the value, or of each item of a list;
 * `list` the value is a list (native array type or `@var X[]`); `listDoc` it is a list by the docblock; `null` the property accepts null; `in` the class that declares
 * the property (when it is not the mapped class); `from` the static factories to call directly, in the order they are tried: `primitive`, `string`.
 */
interface ClassMaps
{
    /** @return array<string, array<string, mixed>>|null */
    public function mapFor(string $class): ?array;
}
