<?php
/** Copyright (c) 2026 Constantin Galbenu <xprt64@gmail.com> */

namespace tests\unit\Gica\Serialize\ObjectHydratorWithObjectTypeTest;

use Gica\Serialize\ObjectHydrator\ObjectHydrator;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\CompositeObjectUnserializer;

class ObjectHydratorWithObjectTypeTest extends \PHPUnit\Framework\TestCase
{
    public function test_a_property_typed_object_keeps_the_raw_value(): void
    {
        $raw = new \stdClass();
        $raw->a = 1;

        $sut = new ObjectHydrator(new CompositeObjectUnserializer([]));

        $reconstructed = $sut->hydrateObject(WithObject::class, ['payload' => $raw]);

        $this->assertSame($raw, $reconstructed->payload);
    }
}

class WithObject
{
    public object $payload;
}
