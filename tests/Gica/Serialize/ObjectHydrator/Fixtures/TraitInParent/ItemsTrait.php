<?php
/** Copyright (c) 2026 Constantin Galbenu <xprt64@gmail.com> */

namespace tests\unit\Fixtures\TraitInParent\Shared;

use tests\unit\Fixtures\TraitInParent\Items\Item;

trait ItemsTrait
{
    /** @var Item[] */
    protected array $items = [];

    public function getItems(): array
    {
        return $this->items;
    }
}
