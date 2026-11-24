<?php
/** Copyright (c) 2026 Constantin Galbenu <xprt64@gmail.com> */

namespace tests\unit\Gica\Serialize\ObjectHydratorWithTraitInParentTest;

use Gica\Serialize\ObjectHydrator\ObjectHydrator;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\CompositeObjectUnserializer;
use tests\unit\Fixtures\TraitInParent\Items\Item;
use tests\unit\Fixtures\TraitInParent\Views\ChildView;

class ObjectHydratorWithTraitInParentTest extends \PHPUnit\Framework\TestCase
{
    public function test_the_class_in_a_docblock_is_resolved_with_the_imports_of_the_trait_of_a_parent_class(): void
    {
        foreach (['Item', 'ItemsTrait', 'BaseWithItems', 'ChildView'] as $file) {
            require_once __DIR__ . "/Fixtures/TraitInParent/$file.php";
        }

        $sut = new ObjectHydrator(new CompositeObjectUnserializer([]));

        $view = $sut->hydrateObject(ChildView::class, ['items' => [['name' => 'a'], ['name' => 'b']]]);

        $this->assertCount(2, $view->getItems());
        $this->assertContainsOnlyInstancesOf(Item::class, $view->getItems());
        $this->assertSame('b', $view->getItems()[1]->name);
    }
}
