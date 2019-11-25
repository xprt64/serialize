<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace tests\unit\Gica\Serialize\ObjectHydratorWithTypedPropertiesTest;


use Gica\Serialize\ObjectHydrator\ObjectHydrator;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\CompositeObjectUnserializer;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\DateTimeImmutableFromString;

class ObjectHydratorWithTypedPropertiesTest extends \PHPUnit_Framework_TestCase
{

    public function test_hydrateObject()
    {
        $dateTimeImmutable = new \DateTimeImmutable("2017-01-02");

        $document = [
            'nestedObject'          => [
                'someNestedVar' => 123,
            ],
            'someInt'               => 456,
            'someString'            => "some str",
            'dateTimeImmutable'     => "2017-01-02",
            'someVar'               => 0.01,
            'someBool'              => true,
            'someArray'             => [4, 6, 8],
            'someNull'              => 'not-null-value',
            'propertyWithDocError'  => 2,
            'propertyWithShortType' => 2,

            'someNonExistingProperty'  => 123,
            'propertyWithUnknownArray' => [1, 2, 3],
        ];

        $sut = new ObjectHydrator(new CompositeObjectUnserializer([new DateTimeImmutableFromString()]));

        /** @var MyObject $reconstructed */
        $reconstructed = $sut->hydrateObject(MyObject::class, $document);

        $this->assertInstanceOf(MyObject::class, $reconstructed);
        $this->assertInstanceOf(MyNestedObject::class, $reconstructed->getNestedObject());
        $this->assertSame($document['nestedObject']['someNestedVar'], $reconstructed->getNestedObject()->getSomeNestedVar());
        $this->assertSame($document['someInt'], $reconstructed->getSomeInt());
        $this->assertSame($document['someString'], $reconstructed->getSomeString());
        $this->assertEquals($dateTimeImmutable, $reconstructed->getDateTimeImmutable());
        $this->assertSame($document['someBool'], $reconstructed->getSomeBool());
    }

    public function test_hydrateObjectProperty()
    {
        $document = [
            'nestedObject' => [
                'someNestedVar' => 123,
            ],
            'someInt'      => 456,
            'someString'   => "some str",
            'someVar'      => 0.01,
            'someFloat'    => 0.02,
            'someBool'     => true,
            'someBoolean'  => false,
            'someArray'    => [4, 6, 8],
            'someNull'     => 'not-null-value',
            'someRealNull' => null,
            'someStrings' => ['a', 'bb', 'ccc']
        ];

        $sut = new ObjectHydrator(new CompositeObjectUnserializer([]));

        $this->assertSame($document['someInt'], $sut->hydrateObjectProperty(MyObject::class, 'someInt', $document['someInt']));
        $this->assertSame($document['someString'], $sut->hydrateObjectProperty(MyObject::class, 'someString', $document['someString']));
        //$this->assertSame($document['someVar'], $sut->hydrateObjectProperty(MyObject::class, 'someVar', $document['someVar']));
        $this->assertSame($document['someArray'], $sut->hydrateObjectProperty(MyObject::class, 'someArray', $document['someArray']));
        $this->assertSame($document['someBool'], $sut->hydrateObjectProperty(MyObject::class, 'someBool', $document['someBool']));
        $this->assertSame($document['someBoolean'], $sut->hydrateObjectProperty(MyObject::class, 'someBoolean', $document['someBoolean']));
        $this->assertSame($document['someNull'], $sut->hydrateObjectProperty(MyObject::class, 'someNull', $document['someNull']));
        $this->assertSame($document['someFloat'], $sut->hydrateObjectProperty(MyObject::class, 'someFloat', $document['someFloat']));
        $this->assertSame($document['someStrings'], $sut->hydrateObjectProperty(MyObject::class, 'someStrings', $document['someStrings']));
        $this->assertNull($sut->hydrateObjectProperty(MyObject::class, 'someNull', null));
        $this->assertNull($sut->hydrateObject('null', 'someRealNull'));
    }
}

class MyObject
{
    private ?MyNestedObject $nestedObject;
    private int $someInt;
    private ?int $someIntOptional;
    private float $someFloat;
    private string $someString;
    private ?\DateTimeImmutable $dateTimeImmutable;
    private bool $someBool;

    /** @var string[] */
    public array $someStrings = [];

    public function __construct(
        ?MyNestedObject $nestedObject,
        int $someInt,
        ?int $someIntOptional,
        float $someFloat,
        ?string $someString,
        ?\DateTimeImmutable $dateTimeImmutable,
        bool $someBool
    )
    {
        $this->nestedObject = $nestedObject;
        $this->someInt = $someInt;
        $this->someString = $someString;
        $this->dateTimeImmutable = $dateTimeImmutable;
        $this->someBool = $someBool;
        $this->someIntOptional = $someIntOptional;
        $this->someFloat = $someFloat;
    }

    public function getNestedObject(): ?MyNestedObject
    {
        return $this->nestedObject;
    }

    public function getSomeInt(): int
    {
        return $this->someInt;
    }

    public function getSomeIntOptional(): ?int
    {
        return $this->someIntOptional;
    }

    public function getSomeFloat(): float
    {
        return $this->someFloat;
    }

    public function getSomeString(): string
    {
        return $this->someString;
    }

    public function getDateTimeImmutable(): ?\DateTimeImmutable
    {
        return $this->dateTimeImmutable;
    }

    public function getSomeBool(): bool
    {
        return $this->someBool;
    }
}

class MyNestedObject
{
    private $someNestedVar;

    public function __construct(
        $someNestedVar
    )
    {
        $this->someNestedVar = $someNestedVar;
    }

    public function getSomeNestedVar()
    {
        return $this->someNestedVar;
    }
}


class a
{
}