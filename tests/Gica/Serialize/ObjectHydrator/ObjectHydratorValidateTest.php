<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace tests\unit\Gica\Serialize\ObjectHydratorValidateTest;


use Gica\Serialize\ObjectHydrator\ObjectHydrator;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\CompositeObjectUnserializer;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\DateTimeImmutableFromString;

class ObjectHydratorValidateTest extends \PHPUnit_Framework_TestCase
{

    public function test_hydrateObject()
    {
        $document = [
            'someInt'               => null,
        ];

        $this->expectException(MyException::class);

        $sut = new ObjectHydrator(new CompositeObjectUnserializer([]));
        $sut->hydrateObject(MyObject::class, $document);
    }
}

class MyObject
{
    /**
     * @var int
     */
    private $someInt = null;

    public function validateSelfOrThrow():bool{
       throw new MyException();
    }
}

class MyException extends \Exception {

}