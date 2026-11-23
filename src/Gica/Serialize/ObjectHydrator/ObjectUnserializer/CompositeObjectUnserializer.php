<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace Gica\Serialize\ObjectHydrator\ObjectUnserializer;


use Gica\Serialize\ObjectHydrator\Exception\AdapterNotFoundException;
use Gica\Serialize\ObjectHydrator\Exception\ValueNotUnserializable;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer;

class CompositeObjectUnserializer implements ObjectUnserializer
{

    /**
     * @var ObjectUnserializer[]
     */
    private $unserializers;
    /** @var array<string, array<int, true>> locators that cannot handle (class, kind of value): the answer never changes */
    private array $skip = [];

    public function __construct(
        array $unserializers = []
    )
    {
        $this->unserializers = $unserializers;
    }

    #[\Override]
    public function tryToUnserializeValue(string $objectClassName, $serializedValue)
    {
        $key = $objectClassName . '|' . get_debug_type($serializedValue);
        foreach ($this->unserializers as $i => $adapterLocator) {
            if (isset($this->skip[$key][$i])) {
                continue;
            }
            try {
                return $adapterLocator->tryToUnserializeValue($objectClassName, $serializedValue);
            } catch (AdapterNotFoundException $exception) {
                $this->skip[$key][$i] = true; // depends on the class and the kind of value only
                continue;
            } catch (ValueNotUnserializable $exception) {
                if ($adapterLocator instanceof \Gica\Serialize\ObjectHydrator\ObjectUnserializer\WithStaticFactory\FromPrimitive || $adapterLocator instanceof \Gica\Serialize\ObjectHydrator\ObjectUnserializer\WithStaticFactory\FromString) {
                    $this->skip[$key][$i] = true; // they only look at the class (has fromPrimitive/fromString) and the kind of value
                }
                continue;
            }
        }

        throw new AdapterNotFoundException(
            sprintf("None of the %d adapter locators could find an adapter", count($this->unserializers)));
    }
}