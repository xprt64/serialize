<?php
/** Copyright (c) 2026 Constantin Galbenu <xprt64@gmail.com>. All rights reserved. Unauthorized copying, use or distribution is prohibited. */

namespace tests\unit\Gica\Serialize\ObjectUnserializer\CompositeObjectUnserializerSkipTest;

use Gica\Serialize\ObjectHydrator\Exception\AdapterNotFoundException;
use Gica\Serialize\ObjectHydrator\Exception\ValueNotUnserializable;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\CompositeObjectUnserializer;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\WithStaticFactory\FromPrimitive;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\WithStaticFactory\FromString;
use PHPUnit\Framework\TestCase;

/** The composite remembers which locators cannot handle a (class, kind of value): that must never hide a later value of ANOTHER kind or a value-dependent answer. */
class CompositeObjectUnserializerSkipTest extends TestCase
{
    public function test_a_locator_without_adapter_for_strings_still_serves_arrays_of_the_same_class()
    {
        $onlyArrays = new class implements ObjectUnserializer {
            public function tryToUnserializeValue(string $objectClassName, $serializedValue)
            {
                if (is_array($serializedValue)) {
                    return 'from array';
                }
                throw new AdapterNotFoundException();
            }
        };
        $sut = new CompositeObjectUnserializer([$onlyArrays]);

        try {
            $sut->tryToUnserializeValue('Some\\Thing', 'a string');
            $this->fail('no adapter for strings');
        } catch (AdapterNotFoundException) {
        }
        $this->assertSame('from array', $sut->tryToUnserializeValue('Some\\Thing', ['x']));
        $this->expectException(AdapterNotFoundException::class);
        $sut->tryToUnserializeValue('Some\\Thing', 'again a string');
    }

    public function test_a_locator_that_refuses_one_value_of_a_kind_still_serves_the_next_value_of_that_kind()
    {
        $onlyDigits = new class implements ObjectUnserializer {
            public function tryToUnserializeValue(string $objectClassName, $serializedValue)
            {
                if (is_string($serializedValue) && ctype_digit($serializedValue)) {
                    return (int)$serializedValue;
                }
                throw new ValueNotUnserializable();
            }
        };
        $sut = new CompositeObjectUnserializer([new FromPrimitive(), new FromString(), $onlyDigits]);

        try {
            $sut->tryToUnserializeValue('Some\\Thing', 'abc');
            $this->fail('not digits');
        } catch (AdapterNotFoundException) {
        }
        $this->assertSame(42, $sut->tryToUnserializeValue('Some\\Thing', '42'));
    }
}
