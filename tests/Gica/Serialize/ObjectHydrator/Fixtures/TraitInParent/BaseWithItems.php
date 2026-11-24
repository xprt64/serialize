<?php
/** Copyright (c) 2026 Constantin Galbenu <xprt64@gmail.com> */

namespace tests\unit\Fixtures\TraitInParent\Base;

use tests\unit\Fixtures\TraitInParent\Shared\ItemsTrait;

abstract class BaseWithItems
{
    use ItemsTrait;
}
