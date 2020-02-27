<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace tests\unit\Gica\Serialize\ObjectHydratorIsNullTest;


use Gica\Serialize\ObjectHydrator\ObjectHydrator;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\CompositeObjectUnserializer;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\DateTimeImmutableFromString;

class ObjectHydratorIsNullTest extends \PHPUnit_Framework_TestCase
{

    public function test_hydrateObject()
    {
        $document = [
            'someInt'               => null,
        ];

        $sut = new ObjectHydrator(new CompositeObjectUnserializer([]));

        /** @var MyObject $reconstructed */
        $reconstructed = $sut->hydrateObject(MyObject::class, $document);

        $this->assertNull($reconstructed);
    }
}

class MyObject
{
    /**
     * @var int
     */
    private $someInt = null;

    public function isNull():bool{
        return $this->someInt === null;
    }
}
