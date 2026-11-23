<?php
/** Copyright (c) 2026 Constantin Galbenu <gica.galbenu@gmail.com>. All rights reserved. */

namespace tests\unit\Gica\Serialize\ObjectHydrator\Compiled;

use Gica\Serialize\ObjectHydrator\Compiled\DirectoryCompiledHydrators;
use Gica\Serialize\ObjectHydrator\Compiled\HydratorCompiler;
use Gica\Serialize\ObjectHydrator\ObjectHydrator;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\CompositeObjectUnserializer;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\WithStaticFactory\FromPrimitive;
use Gica\Serialize\ObjectHydrator\ObjectUnserializer\WithStaticFactory\FromString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Money
{
    public function __construct(public readonly string $value)
    {
    }

    public static function fromString(string $v): self
    {
        return new self($v);
    }
}

class Qty
{
    public function __construct(public readonly string $shown)
    {
    }

    public static function fromPrimitive($v): self
    {
        return new self('prim:' . json_encode($v));
    }
}

class Both
{
    public function __construct(public readonly string $shown)
    {
    }

    public static function fromPrimitive($v): self
    {
        return new self('prim:' . json_encode($v));
    }

    public static function fromString(string $v): self
    {
        return new self('str:' . $v);
    }
}

class Leaf
{
    private int $n = 7;
    private string $label = 'default label';
    /** @var string[] */
    private array $tags = ['default'];
}

class Branch
{
    private string $name = '';
    /** @var Leaf[] */
    private array $leaves = [];
    private ?Leaf $main = null;
}

class Base
{
    protected string $inherited = 'base';
    protected ?int $inheritedNumber = null;
}

class Child extends Base
{
    private string $own = '';
    private bool $flag = false;
    private float $ratio = 0.5;
    private int $count = 0;
    private $untyped = 'u';
    private ?string $nullable = 'not null';
    private string $notNullable = 'keep me';
    private Money $price;
    /** @var Money[] */
    private array $prices = [];
    private array $rawArray = [];
    private object $anything;
    private mixed $whatever = null;
    private Branch $branch;
    /** @var Branch[] */
    private array $branches = [];
    private ?Leaf $leaf = null;
    private Qty $qty;
    private Both $both;
    /** @var Qty[] */
    private array $qtys = [];
    /** @var Both[] */
    private array $boths = [];
    /** @var string[] */
    private array $words = [];
    /** @var int[] */
    private array $numbers = [];
}

class ParentWithPrivate
{
    private string $secret = 'p';
}

class ChildOfPrivate extends ParentWithPrivate
{
    private string $visible = 'v';
}

class ParentWithReadonly
{
    public function __construct(protected readonly string $fixed = 'f', private readonly int $hidden = 1)
    {
    }
}

class ChildOfReadonly extends ParentWithReadonly
{
    private string $extra = 'e';
}

class GrandParent
{
    private string $deep = 'g';
    private string $same = 'grand';
}

class MiddleClass extends GrandParent
{
    private string $middle = 'm';
    protected string $same = 'middle'; // hides the private $same of the grandparent
}

class Leafmost extends MiddleClass
{
    private ?Leaf $leaf = null;
}

class WithUnion
{
    private int|string $either = 0;
    private string $plain = '';
}

class WithHooks
{
    private string $name = '';
    private bool $empty = false;

    public function isNull(): bool
    {
        return $this->empty;
    }

    public function validateSelfOrThrow(): void
    {
        if ($this->name === 'forbidden') {
            throw new \DomainException('forbidden');
        }
    }
}

class CompiledHydratorTest extends TestCase
{
    private const CLASSES = [Qty::class, Both::class, Money::class, Leaf::class, Branch::class, Base::class, Child::class, ParentWithPrivate::class, ChildOfPrivate::class, WithUnion::class, WithHooks::class, ParentWithReadonly::class, ChildOfReadonly::class, GrandParent::class, MiddleClass::class, Leafmost::class, \stdClass::class];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/compiled-hydrators-' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);
    }

    private function unserializer(): CompositeObjectUnserializer
    {
        return new CompositeObjectUnserializer([new FromPrimitive(), new FromString()]);
    }

    private function hydrators(bool $standardFactories = false): array
    {
        $reference = new ObjectHydrator($this->unserializer());
        $result = (new HydratorCompiler($reference, $standardFactories))->writeDirectory($this->dir, self::CLASSES);
        return [$reference, new ObjectHydrator($this->unserializer(), new DirectoryCompiledHydrators($this->dir)), $result];
    }

    public function test_classes_that_cannot_be_compiled_are_skipped_and_the_others_are_written()
    {
        [, , $result] = $this->hydrators();
        $this->assertContains(Child::class, $result['compiled']);
        $this->assertContains(Leaf::class, $result['compiled']);
        $this->assertContains(Money::class, $result['compiled']);
        $this->assertContains(ChildOfPrivate::class, $result['compiled'], 'private properties of ancestors are set by a function bound to the ancestor');
        $this->assertContains(ChildOfReadonly::class, $result['compiled']);
        $this->assertContains(Leafmost::class, $result['compiled']);
        $this->assertArrayHasKey(\stdClass::class, $result['skipped'], 'internal classes cannot be bound');
        $this->assertArrayHasKey(WithUnion::class, $result['skipped'], 'union types are left to the reflection path');
    }

    #[DataProvider('documents')]
    public function test_the_compiled_hydrator_builds_exactly_what_the_reflection_one_builds(string $class, $document)
    {
        [$reference, $compiled] = $this->hydrators();
        $this->assertSame($this->outcome($reference, $class, $document), $this->outcome($compiled, $class, $document));
    }

    #[DataProvider('documents')]
    public function test_with_the_static_factories_inlined_the_result_is_still_the_same(string $class, $document)
    {
        [$reference, $compiled] = $this->hydrators(true);
        $this->assertSame($this->outcome($reference, $class, $document), $this->outcome($compiled, $class, $document));
    }

    private function outcome(ObjectHydrator $hydrator, string $class, $document): string
    {
        try {
            $object = $hydrator->hydrateObject($class, $document);
            return $object === null ? 'null' : serialize($object);
        } catch (\Throwable $e) {
            return get_class($e) . ': ' . $e->getMessage();
        }
    }

    public static function documents(): iterable
    {
        $leaf = ['n' => 3, 'label' => 'L', 'tags' => ['x', 'y']];
        $branch = ['name' => 'B', 'leaves' => [$leaf, ['n' => '9'], []], 'main' => $leaf];
        $child = [
            'inherited' => 'changed', 'inheritedNumber' => '12', 'own' => 'o', 'flag' => '1', 'ratio' => '0.25', 'count' => '5', 'untyped' => ['a' => 1],
            'nullable' => null, 'notNullable' => null, 'price' => 'EUR 5', 'prices' => ['EUR 1', 'EUR 2'], 'rawArray' => [1, 2],
            'anything' => new \ArrayObject(['k' => 'v']), 'whatever' => 3, 'branch' => $branch, 'branches' => [$branch, $branch], 'leaf' => $leaf,
        ];
        $stringable = new class {
            public function __toString(): string
            {
                return 'stringable';
            }
        };
        yield 'factories: strings, ints, arrays' => [Child::class, ['price' => 'EUR 1', 'qty' => 5, 'both' => 'text', 'qtys' => [1, 'a', [2]], 'boths' => ['x', 7, ['k' => 'v'], null], 'words' => ['a', 5, 'c'], 'numbers' => ['1', 2, 'x']]];
        yield 'factories: scalars of each kind' => [Child::class, ['qty' => 'str', 'both' => 12, 'price' => 3.5]];
        yield 'factories: arrays' => [Child::class, ['qty' => ['a' => 1], 'both' => ['a'], 'price' => ['not', 'a', 'string']]];
        yield 'factories: a Stringable object' => [Child::class, ['price' => $stringable, 'both' => $stringable, 'qty' => $stringable]];
        yield 'factories: a non stringable object' => [Child::class, ['price' => new \stdClass(), 'both' => new \ArrayObject([1]), 'qty' => new \ArrayObject([1])]];
        yield 'factories: hints equal to the declared class' => [Child::class, ['price' => 'EUR 1', 'qty' => 5, 'both' => 'text', 'boths' => ['x', 7], '@classes' => ['price' => Money::class, 'qty' => Qty::class, 'both' => Both::class, 'boths' => Both::class]]];
        yield 'factories: hints naming ANOTHER class' => [Child::class, ['price' => 'EUR 1', 'qty' => 'q', 'both' => 'b', '@classes' => ['price' => Both::class, 'qty' => Both::class, 'both' => Qty::class]]];
        yield 'factories: null values' => [Child::class, ['qty' => null, 'both' => null, 'qtys' => null, 'words' => null]];
        yield 'leaf complete' => [Leaf::class, $leaf];
        yield 'leaf empty keeps the defaults' => [Leaf::class, []];
        yield 'leaf with unknown keys and wrong types' => [Leaf::class, ['n' => 'abc', 'zzz' => 1, 'label' => 55, 'tags' => 'not a list']];
        yield 'leaf as ArrayObject' => [Leaf::class, new \ArrayObject($leaf)];
        yield 'branch nested' => [Branch::class, $branch];
        yield 'branch with null main' => [Branch::class, ['name' => 'x', 'main' => null]];
        yield 'child everything' => [Child::class, $child];
        yield 'child as ArrayObject (nested stay arrays)' => [Child::class, new \ArrayObject($child)];
        yield 'child names the classes itself (@classes)' => [Child::class, $child + ['@classes' => ['price' => Money::class, 'prices' => Money::class, 'leaf' => Leaf::class, 'branch' => Branch::class]]];
        yield 'child @classes as ArrayObject' => [Child::class, ['price' => 'EUR 9', '@classes' => new \ArrayObject(['price' => Money::class])]];
        yield 'child @classes with an empty name' => [Child::class, ['price' => 'EUR 9', '@classes' => ['price' => '']]];
        yield 'child nulls only' => [Child::class, ['own' => null, 'flag' => null, 'untyped' => null, 'nullable' => null, 'leaf' => null]];
        yield 'child booleans' => [Child::class, ['flag' => 'true']];
        yield 'child booleans 0' => [Child::class, ['flag' => '0']];
        yield 'child bad int' => [Child::class, ['count' => '5x']];
        yield 'child empty' => [Child::class, []];
        yield 'with private ancestor' => [ChildOfPrivate::class, ['secret' => 's', 'visible' => 'x']];
        yield 'readonly ancestor' => [ChildOfReadonly::class, ['fixed' => 'F', 'hidden' => 5, 'extra' => 'x']];
        yield 'private two levels up' => [Leafmost::class, ['deep' => 'D', 'middle' => 'M', 'same' => 'S', 'leaf' => ['n' => 2]]];
        yield 'private two levels up, empty' => [Leafmost::class, []];
        yield 'with union (not compiled)' => [WithUnion::class, ['either' => 'a', 'plain' => 'p']];
        yield 'hooks normal' => [WithHooks::class, ['name' => 'ok']];
        yield 'hooks isNull' => [WithHooks::class, ['name' => 'ok', 'empty' => true]];
        yield 'hooks validate throws' => [WithHooks::class, ['name' => 'forbidden']];
        yield 'money from string' => [Money::class, 'EUR 3'];
    }

    public function test_the_signature_changes_with_the_source_and_is_stable()
    {
        $c = new HydratorCompiler(new ObjectHydrator($this->unserializer()));
        $this->assertSame($c->signature(Child::class), $c->signature(Child::class));
        $this->assertNotSame($c->signature(Child::class), $c->signature(Leaf::class)); // same file, different ancestors: at least the class names differ
    }

    public function test_a_class_without_a_hydrator_still_works()
    {
        $hydrator = new ObjectHydrator($this->unserializer(), new DirectoryCompiledHydrators($this->dir . '/does-not-exist'));
        $this->assertInstanceOf(Leaf::class, $hydrator->hydrateObject(Leaf::class, ['n' => 1]));
    }
}
