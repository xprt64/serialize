<?php
/** Copyright (c) 2026 Constantin Galbenu <gica.galbenu@gmail.com>. All rights reserved. */

namespace Gica\Serialize\ObjectHydrator\Compiled;

/**
 * Reads the hydrators written by HydratorCompiler::writeDirectory(): `index.php` (class => file) and one file per class. Everything is plain PHP, so opcache keeps it;
 * there is no stat/is_file per class, an unknown class is one array lookup.
 */
class DirectoryCompiledHydrators implements CompiledHydrators
{
    /** @var array<string, string>|null */
    private ?array $index = null;
    /** @var array<string, \Closure> */
    private array $loaded = [];

    public function __construct(private readonly string $directory)
    {
    }

    #[\Override]
    public function hydratorFor(string $class): ?\Closure
    {
        if (isset($this->loaded[$class])) {
            return $this->loaded[$class];
        }
        $this->index ??= is_file($this->directory . '/index.php') ? (require $this->directory . '/index.php') : [];
        if (!isset($this->index[$class])) {
            return null;
        }
        return $this->loaded[$class] = require $this->directory . '/' . $this->index[$class];
    }
}
