<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace tests\Gica\Serialize\ObjectHydrator\AdapterLocator {

    use Gica\Serialize\ObjectHydrator\AdapterLocator\LocalAdapterLocator;
    use Gica\Serialize\ObjectHydrator\Exception\AdapterNotFoundException;

    class LocalAdapterLocatorTest extends \PHPUnit\Framework\TestCase
    {

        public function test()
        {
            $sut = new class extends LocalAdapterLocator {
                protected function getNamespace(): string
                {
                    return 'SomeNamespace';
                }
            };

            /** @var LocalAdapterLocator $sut */
            $this->assertSame(234, $sut->tryToUnserializeValue('SomeClass', 123));

            $this->assertSame(234, $sut->tryToUnserializeValue('SomeClass', new \stdClass()));
        }

        public function test_AdapterNotFoundException()
        {
            $this->expectException(AdapterNotFoundException::class);

            $sut = new class extends LocalAdapterLocator {
                protected function getNamespace(): string
                {
                    return 'SomeNamespaceThatDoNotExists';
                }
            };

            /** @var LocalAdapterLocator $sut */
            $sut->tryToUnserializeValue('SomeClass', 123);
        }
    }

}

namespace SomeNamespace\SomeClass {

    use Gica\Serialize\ObjectHydrator\ObjectUnserializer;

    class Frominteger implements ObjectUnserializer
    {

        /**
         * @inheritdoc
         */
        public function tryToUnserializeValue(string $objectClassName, $serializedValue)
        {
            return 234;
        }
    }

    class FromstdClass implements ObjectUnserializer
    {

        /**
         * @inheritdoc
         */
        public function tryToUnserializeValue(string $objectClassName, $serializedValue)
        {
            return 234;
        }
    }
}