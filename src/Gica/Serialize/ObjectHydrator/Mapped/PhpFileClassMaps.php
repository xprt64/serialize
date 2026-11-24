<?php
/** Copyright (c) 2026 Constantin Galbenu <gica.galbenu@gmail.com>. All rights reserved. */

namespace Gica\Serialize\ObjectHydrator\Mapped;

/** Reads the single PHP file written by ClassMapBuilder::export(): an array literal, so opcache keeps it in shared memory and a lookup is one array access. */
class PhpFileClassMaps implements ClassMaps
{
    /** @var array<string, array<string, array<string, mixed>>>|null */
    private ?array $maps = null;

    public function __construct(private readonly string $file)
    {
    }

    #[\Override]
    public function mapFor(string $class): ?array
    {
        $this->maps ??= is_file($this->file) ? (require $this->file) : [];
        return $this->maps[$class] ?? null;
    }
}
