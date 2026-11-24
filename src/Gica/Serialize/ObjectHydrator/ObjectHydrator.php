<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace Gica\Serialize\ObjectHydrator;


use Gica\CodeAnalysis\Shared\FqnResolver;
use Gica\Serialize\ObjectHydrator\Exception\AdapterNotFoundException;
use Gica\Serialize\ObjectHydrator\Exception\ValueNotScalar;

class ObjectHydrator
{
    /**
     * @var ObjectUnserializer
     */
    private $objectUnserializer;

    public function __construct(
        ObjectUnserializer $objectUnserializer
    )
    {
        $this->objectUnserializer = $objectUnserializer;
    }

    /**
     * @param string $objectClass
     * @param $serializedValue
     * @return object
     */
    private const BUILTIN = ['string' => 1, 'mixed' => 1, 'int' => 1, 'float' => 1, 'bool' => 1, 'boolean' => 1, 'null' => 1];

    public function hydrateObject(string $objectClass, $serializedValue)
    {
        if (isset(self::BUILTIN[$objectClass])) {
            return $this->castValueToBuiltinType($objectClass, $serializedValue);
        }
        try {
            return $this->objectUnserializer->tryToUnserializeValue($objectClass, $serializedValue);
        } catch (AdapterNotFoundException $exception) {
            return $this->hydrateObjectByReflection($objectClass, $serializedValue);
        }
    }

    private function hydrateObjectByReflection(string $objectClass, $document)
    {
        static $reflections = [];
        $reflectionClass = $reflections[$objectClass] ??= new \ReflectionClass($objectClass);
        $object = unserialize($this->getEmptyObject($objectClass));

        $this->matchAndSetNonConstructorProperties($reflectionClass, $object, $document);

        if (is_callable([$object, 'isNull'])) {
            if ($object->isNull()) {
                return null;
            }
        }

        if (is_callable([$object, 'validateSelfOrThrow'])) {
            $object->validateSelfOrThrow();
        }

        return $object;
    }

    private function getEmptyObject(string $className)
    {
        return 'O:' . strlen($className) . ':"' . $className . '":0:{}';
    }

    private function getClassProperty(\ReflectionClass $reflectionClass, string $propertyName): ?\ReflectionProperty
    {
        static $cache = [];
        $cache_id = $reflectionClass->getName() . '::' . $propertyName;
        if (!isset($cache[$cache_id])) {
            $cache[$cache_id] = $this->_getClassProperty($reflectionClass, $propertyName);
        }
        return $cache[$cache_id];
    }

    private function _getClassProperty(\ReflectionClass $reflectionClass, string $propertyName): ?\ReflectionProperty
    {
        if (!$reflectionClass->hasProperty($propertyName)) {

            $parentClass = $reflectionClass->getParentClass();
            if (!$parentClass || !($property = $this->getClassProperty($parentClass, $propertyName))) {
                throw new \Exception("class {$reflectionClass->name} does not contain the property {$propertyName}");
            }

            return $property;
        }

        return $reflectionClass->getProperty($propertyName);
    }

    private function castValueToBuiltinType($type, $value)
    {
        switch ((string)$type) {
            case 'string':
                return strval($value);

            case 'mixed':
                return $value;

            case 'int':
                if (\is_int($value)) {
                    return $value;
                }
                if (is_string($value) && ($value === (string)((int)$value))) {
                    return (int)$value;
                }
                return null;

            case 'float':
                return floatval($value);

            case 'bool':
            case 'boolean':
                if (\is_bool($value)) {
                    return $value;
                }
                if ($value === '1' || $value === 'true') {
                    return true;
                } else {
                    if ($value === '0' || $value === 'false') {
                        return false;
                    }
                }
                return null;

            case 'null':
                return null;
        }

        throw new ValueNotScalar("Unknown builtin type: $type");
    }

    private function detectIfPropertyIsArrayFromComment(\ReflectionClass $reflectionClass, string $propertyName)
    {
        static $cache = [];
        $cacheId = $reflectionClass->getName() . '-' . $propertyName;
        if (!isset($cache[$cacheId])) {
            $cache[$cacheId] = $this->_detectIfPropertyIsArrayFromComment($reflectionClass, $propertyName);
        }
        return $cache[$cacheId];
    }

    private function _detectIfPropertyIsArrayFromComment(\ReflectionClass $reflectionClass, string $propertyName)
    {
        $shortType = $this->parseTypeFromPropertyVarDoc($reflectionClass, $propertyName);
        if (null === $shortType) {
            return false;
        }
        $len = strlen($shortType);
        if ($len < 3) {
            return false;
        }
        return $shortType[$len - 2] === '[' && $shortType[$len - 1] === ']';
    }

    private function detectClassNameFromPropertyComment(\ReflectionClass $reflectionClass, string $propertyName)
    {
        static $cache = [];
        $cacheId = $reflectionClass->getName() . '-' . $propertyName;
        if (!isset($cache[$cacheId])) {
            $cache[$cacheId] = $this->_detectClassNameFromPropertyComment($reflectionClass, $propertyName);
        }
        return $cache[$cacheId];
    }

    private function _detectClassNameFromPropertyComment(\ReflectionClass $reflectionClass, string $propertyName)
    {
        $shortType = $this->parseTypeFromPropertyVarDoc($reflectionClass, $propertyName);
        $shortType = rtrim((string)$shortType, '[]');
        if ($shortType === '' || null === $shortType) {
            return null;
        }
        if ('array' === $shortType) {
            return null;
        }
        if ('\\' == $shortType[0]) {
            return ltrim($shortType, '\\');
        }
        if ($this->isScalar($shortType)) {
            return $shortType;
        }

        return ltrim($this->resolveShortClassName($shortType, $reflectionClass), '\\');
    }

    /** the short name of a docblock type, resolved with the imports of the class, of its traits, then of its parent classes and their traits (a property may be declared up there) */
    private function resolveShortClassName($shortName, \ReflectionClass $contextClass)
    {
        $className = new FqnResolver()->resolveShortClassName($shortName, $contextClass);
        for ($class = $contextClass; $class && !class_exists($className); $class = $class->getParentClass()) {
            if ($class !== $contextClass) {
                $className = new FqnResolver()->resolveShortClassName($shortName, $class);
            }
            foreach ($class->getTraits() as $trait) {
                if (class_exists($className)) {
                    break;
                }
                $className = new FqnResolver()->resolveShortClassName($shortName, $trait);
            }
        }
        return $className;
    }

    private function matchAndSetNonConstructorProperties(\ReflectionClass $reflectionClass, $object, $document): void
    {
        foreach ($document as $propertyName => $value) {
            if ('@classes' === $propertyName) {
                continue;
            }

            try {
                $actualClassName = isset($document['@classes'][$propertyName]) ? $document['@classes'][$propertyName] : null;

                $result = $this->hydrateProperty($reflectionClass, $propertyName, $value, $actualClassName);

                $property = $this->getClassProperty($reflectionClass, $propertyName);
                if (null === $result && ($property->getType() && !$property->getType()->allowsNull())) {
                    continue;
                }
                $property->setValue($object, $result);
            } catch (\Exception $exception) {
                continue;
            }
        }
    }

    public function hydrateObjectProperty($objectClass, string $propertyName, $document)
    {
        $reflectionClass = new \ReflectionClass($objectClass);
        return $this->hydrateProperty($reflectionClass, $propertyName, $document);
    }

    private function hydrateProperty(\ReflectionClass $reflectionClass, string $propertyName, $document, ?string $actualClassName = null)
    {
        if (null === $document) {
            return null;
        }

        if (!$actualClassName) {
            $planKey = $reflectionClass->name . '::' . $propertyName;
            $plan = self::$plans[$planKey] ??= $this->computePropertyPlan($reflectionClass, $propertyName);
            [$builtin, $propertyClassName, $isArray] = $plan;
            if (null !== $builtin) {
                return $this->castValueToBuiltinType($builtin, $document);
            }
            if (null === $propertyClassName) {
                return $document;
            }
        } else {
            $propertyClassName = $actualClassName;
            $isArray = $this->detectIfPropertyIsArrayFromComment($reflectionClass, $propertyName);
        }
        if ($isArray) {
            $result = [];
            foreach ($document as $k => $item) {
                $result[$k] = $this->hydrateObject($propertyClassName, $item);
            }
        } else {
            $result = $this->hydrateObject($propertyClassName, $document);
        }

        return $result;
    }

    private static array $plans = [];

    /** @return array{0: ?string, 1: ?string, 2: bool} [builtin type to cast to, class name of the value(s), is a list] - the same decisions hydrateProperty took on every call */
    private function computePropertyPlan(\ReflectionClass $reflectionClass, string $propertyName): array
    {
        $isArray = null;
        try {
            $reflectionType = $this->detectClassNameFromPropertyType($reflectionClass, $propertyName);
            $reflectionTypeString = null;
            if ($reflectionType) {
                $isArray = false;
                if ($reflectionType instanceof \ReflectionNamedType) {
                    $reflectionTypeString = $reflectionType->getName();
                } else {
                    $reflectionTypeString = @$reflectionType->__toString();
                }
                if ($reflectionType->isBuiltin()) {
                    if ($reflectionTypeString === 'array') {
                        $isArray = true;
                        unset($reflectionTypeString);
                    } elseif (isset(self::BUILTIN[$reflectionTypeString])) {
                        return [$reflectionTypeString, null, false];
                    } else {
                        return [null, null, false]; // object, iterable, callable, ...: the cast threw ValueNotScalar and the raw value was kept
                    }
                }
            }
            $propertyClassName = $reflectionTypeString ?? $this->detectClassNameFromPropertyComment($reflectionClass, $propertyName);
        } catch (\Exception $exception) {
            $propertyClassName = null;
        }
        if (null === $propertyClassName || 'null' === $propertyClassName) {
            return [null, null, false];
        }
        if (null === $isArray) {
            $isArray = $this->detectIfPropertyIsArrayFromComment($reflectionClass, $propertyName);
        }
        return [null, $propertyClassName, $isArray];
    }


    /**
     * @param $shortType
     * @return bool
     */
    private function isScalar($shortType): bool
    {
        static $cache = [];
        $cacheId = $shortType;
        if (!isset($cache[$cacheId])) {
            $cache[$cacheId] = $this->_isScalar($shortType);
        }
        return $cache[$cacheId];
    }

    /**
     * @param $shortType
     * @return bool
     */
    private function _isScalar($shortType): bool
    {
        try {
            $this->castValueToBuiltinType($shortType, '1');
            return true;
        } catch (ValueNotScalar $exception) {
            return false;
        }
    }

    private function parseTypeFromPropertyVarDoc(\ReflectionClass $reflectionClass, string $propertyName)
    {
        $property = $this->getClassProperty($reflectionClass, $propertyName);

        if (!preg_match('#\@var\s+(?P<shortType>[\\\\a-z0-9_\]\[]+)#ims', $property->getDocComment() ?? '', $m)) {
            return null;
        }
        return $m['shortType'];
    }

    private function detectClassNameFromPropertyType(\ReflectionClass $reflectionClass, string $propertyName): ?\ReflectionType
    {
        $property = $this->getClassProperty($reflectionClass, $propertyName);
        if (!$property) {
            return null;
        }
        if ($property->hasType()) {
            return $property->getType();
        }
        return null;
    }
}