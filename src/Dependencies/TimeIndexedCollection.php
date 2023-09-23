<?php

declare(strict_types=1);

namespace App\Dependencies;

use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Countable;
use Generator;
use IteratorAggregate;
use Traversable;
use Webmozart\Assert\Assert;
use function App\Functional\concat;
use function App\Functional\filter;
use function App\Functional\map;
use function App\Functional\skeys;
use function App\Functional\slist;
use function App\Functional\sreduce;

/**
 * This collection is effectively immutable as there is no method with visible
 * mutating behavior.
 *
 * @template T
 * @implements \IteratorAggregate<LocalDateTimeInterval, T>
 */
final class TimeIndexedCollection implements IteratorAggregate, Countable
{
    /**
     * @param Node<T>|null $root
     */
    private function __construct(
        private ?Node $root = null,
    ) {
    }

    /**
     * A new empty collection.
     *
     * @return self<never>
     */
    public static function empty(): self
    {
        /** @var self<never> */
        return new self();
    }

    /**
     * Imports values into this collection.
     *
     * @template K of LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval
     * @template U
     *
     * @param iterable<U> $values
     * @param callable(U): (K|iterable<K>) $keyFn
     *
     * @return self<U>
     */
    public static function import(iterable $values, callable $keyFn): self
    {
        /** @var self<U> $collection */
        $collection = self::empty();
        foreach ($values as $value) {
            $result = $keyFn($value);
            /** @var K $range */
            foreach (is_array($result) || $result instanceof Generator ? $result : [$result] as $range) {
                $collection->insertRoot($range, [$value]);
            }
        }

        return $collection;
    }

    /**
     * Imports values into this collection.
     *
     * @template K of LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval
     * @template U
     * @template V
     *
     * @param iterable<U> $values
     * @param callable(U): iterable<K, V> $fn
     *
     * @return self<V>
     */
    public static function collect(iterable $values, callable $fn): self
    {
        /** @var self<V> $collection */
        $collection = self::empty();
        foreach ($values as $value) {
            /** @var K $range */
            foreach ($fn($value) as $range => $mappedValue) {
                $collection->insertRoot($range, [$mappedValue]);
            }
        }

        return $collection;
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<T|U>
     */
    public function add(LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $range, mixed $value): self
    {
        // Respect immutability
        $collection = self::cloneFrom($this);

        /** @phpstan-ignore-next-line This type is very hard to get right because of mutability */
        $collection->insertRoot($range, [$value]);

        /** @var self<T|U> */
        return $collection;
    }

    public function isEmpty(): bool
    {
        return null === $this->root;
    }

    public function isNotEmpty(): bool
    {
        return null !== $this->root;
    }

    /**
     * @template U
     * @template V
     *
     * @param callable(U|V, T, LocalDateTimeInterval): V $fn
     * @param U $initial
     *
     * @return U|V
     */
    public function reduce(callable $fn, mixed $initial = null): mixed
    {
        return sreduce($this, $fn, $initial);
    }

    /**
     * @template U
     *
     * @param callable(T): U $fn
     *
     * @return self<U>
     */
    public function map(callable $fn): self
    {
        $clone = self::cloneFrom($this);

        foreach (self::iterateNodes($clone->root, null) as $node) {
            /** @var T[] $items Modifying $node->items' types to U[] doesn't work well with static analysis */
            $items = map($node->items, $fn);
            $node->items = $items;
        }

        /** @var self<U> This one is impossible to type due to mutability of $node above */
        return $clone;
    }

    /**
     * @param (callable(T): bool)|null $predicate
     *
     * @return self<T>
     */
    public function filter(?callable $predicate = null): self
    {
        /** @var self<T> $collection */
        $collection = self::empty();

        foreach (self::iterateNodes($this->root, null) as $node) {
            $items = filter($node->items, $predicate);
            if (!empty($items)) {
                $collection->insertRoot($node->interval, $items);
            }
        }

        return $collection;
    }

    /**
     * @return self<T>
     */
    public function slice(LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $temporal): self
    {
        /** @var self<T> $collection */
        $collection = self::empty();

        foreach (self::iterateNodes($this->root, $temporal) as $node) {
            $collection->insertRoot($node->interval, $node->items);
        }

        return $collection;
    }

    /**
     * @return Generator<LocalDateTimeInterval, T>
     */
    public function stream(null|LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $temporal = null): Generator
    {
        foreach (self::iterateNodes($this->root, $temporal) as $node) {
            // We cannot use "yield from $node->items" or it will emit key-values with the same indexes
            foreach ($node->items as $item) {
                yield $node->interval => $item;
            }
        }
    }

    public function count(null|LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $temporal = null): int
    {
        /** @var int<0, max> */
        return sreduce(
            self::iterateNodes($this->root, $temporal),
            static fn (int $i, Node $node): int => $i + count($node->items),
            initial: 0,
        );
    }

    /**
     * @return list<LocalDateTimeInterval>
     */
    public function keys(null|LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $temporal = null): array
    {
        return slist(skeys($this->stream($temporal)));
    }

    /**
     * @return list<T>
     */
    public function values(null|LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $temporal = null): array
    {
        return slist($this->stream($temporal));
    }

    public function span(): ?LocalDateTimeInterval
    {
        // Empty collections have no time range
        if (null === $this->root) {
            return null;
        }

        // Find the left-most node
        $current = $this->root;
        while (null !== $current->left) {
            $current = $current->left;
        }

        return LocalDateTimeInterval::between($current->interval->getStart(), $this->root->max);
    }

    /**
     * @return Traversable<LocalDateTimeInterval, T>
     */
    public function getIterator(): Traversable
    {
        return $this->stream();
    }

    /**
     * @param T[] $values
     */
    private function insertRoot(LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $key, array $values): void
    {
        $this->root = self::insert($this->root, LocalDateTimeInterval::cast($key), $values);
        $this->root->color = Node::BLACK;
    }

    /**
     * This method mutate $node. Care should be taken to not call this method once
     * a complete collection is finalized and returned to the caller.
     * Mutating the tree during construction phase is fine though! 👌
     *
     * @template U
     *
     * @param Node<U>|null $node
     * @param U[] $items
     *
     * @return Node<U>
     */
    private static function insert(?Node $node, LocalDateTimeInterval $key, array $items, ?bool &$maxUpdated = null): Node
    {
        if (null === $node) {
            $maxUpdated = true;

            return new Node($key, $items);
        }

        if (self::isRed($node->left) && self::isRed($node->right)) {
            self::flipColors($node);
        }

        switch ($key->compareTo($node->interval)) {
            case -1:
                $node->left = self::insert($node->left, $key, $items, $leftMaxUpdated);

                if (true === $leftMaxUpdated) {
                    $nodeMax = $node->max;
                    $leftMax = $node->left->max;
                    if (null !== $nodeMax && (null === $leftMax || $leftMax->isAfter($nodeMax))) {
                        $node->max = $leftMax;
                        $maxUpdated = true;
                    }
                }
                break;
            case 0:
                $node->items = concat($node->items, $items);

                break;
            case 1:
                $node->right = self::insert($node->right, $key, $items, $rightMaxUpdated);

                if (true === $rightMaxUpdated) {
                    $nodeMax = $node->max;
                    $rightMax = $node->right->max;
                    if (null !== $nodeMax && (null === $rightMax || $rightMax->isAfter($nodeMax))) {
                        $node->max = $rightMax;
                        $maxUpdated = true;
                    }
                }
                break;
        }

        if (self::isRed($node->right) && !self::isRed($node->left)) {
            $node = self::rotateLeft($node);
        }

        if (self::isRed($node->left) && (null !== $node->left && self::isRed($node->left->left))) {
            $node = self::rotateRight($node);
        }

        return $node;
    }

    /**
     * Depth First Traversal of every node from left to right that intersects
     * the given time range (if given).
     *
     * @see https://en.wikipedia.org/wiki/Interval_tree#Java_example:_Searching_a_point_or_an_interval_in_the_tree
     *
     * @template U
     *
     * @param Node<U>|null $node
     *
     * @return iterable<Node<U>>
     */
    private static function iterateNodes(?Node $node, null|LocalDate|LocalDateTime|LocalDateInterval|LocalDateTimeInterval $temporal): iterable
    {
        // Don't search node that don't exist
        if (null === $node) {
            return;
        }

        $timeRange = LocalDateTimeInterval::cast($temporal);

        // If the search interval is after the right-most point in this subtree
        // there is no hope of finding a match!
        if (null !== $timeRange &&
            null !== $node->max &&
            !$timeRange->hasInfiniteStart() &&
            $timeRange->getFiniteStart()->isAfter($node->max)
        ) {
            return;
        }

        // Search left children
        yield from self::iterateNodes($node->left, $timeRange);

        // Check this node
        if (null === $timeRange || $node->interval->intersects($timeRange)) {
            yield $node;
        }

        // If the search interval is to the left of this node interval, it
        // can't possibly match with any right children.
        if (null !== $timeRange &&
            !$timeRange->hasInfiniteEnd() &&
            !$node->interval->hasInfiniteStart() &&
            $timeRange->getFiniteEnd()->isBefore($node->interval->getFiniteStart())
        ) {
            return;
        }

        // Search right children
        yield from self::iterateNodes($node->right, $timeRange);
    }

    /**
     * Tests if the given node is test. By convention a NULL node is black.
     *
     * @template U
     *
     * @param Node<U>|null $node
     */
    private static function isRed(?Node $node): bool
    {
        return null !== $node && Node::RED === $node->color;
    }

    /**
     * Performs a left rotation of the node.
     *
     *   [2]             4
     *   / \            / \
     *  1   4    =>   [2]  5
     *     / \        / \
     *    3   5      1   3
     *
     * @template U
     *
     * @param Node<U> $node
     *
     * @return Node<U>
     */
    private static function rotateLeft(Node $node): Node
    {
        $x = $node->right;
        Assert::notNull($x, sprintf('%s expects a non-null right child', __METHOD__));

        $node->right = $x->left;
        $x->left = $node;
        $x->color = $node->color;
        $node->color = Node::RED;

        $node->updateMax();
        $x->updateMax();

        return $x;
    }

    /**
     * Performs a right rotation of the node.
     *
     *     [4]         2
     *     / \        / \
     *    2   5  =>  1  [4]
     *   / \            / \
     *  1   3          3   5
     *
     * @template U
     *
     * @param Node<U> $node
     *
     * @return Node<U>
     */
    private static function rotateRight(Node $node): Node
    {
        $x = $node->left;
        Assert::notNull($x, sprintf('%s expects a non-null left child', __METHOD__));

        $node->left = $x->right;
        $x->right = $node;
        $x->color = $node->color;
        $node->color = Node::RED;

        $node->updateMax();
        $x->updateMax();

        return $x;
    }

    /**
     * @template U
     *
     * @param Node<U> $node
     */
    private static function flipColors(Node $node): void
    {
        $left = $node->left;
        Assert::notNull($left, sprintf('%s expects a non-null left child', __METHOD__));

        $right = $node->right;
        Assert::notNull($right, sprintf('%s expects a non-null right child', __METHOD__));

        $node->color = !$node->color;
        $left->color = !$left->color;
        $right->color = !$right->color;
    }

    /**
     * Constructs a clone of this collection. Common use-case is as a first step before bulk mutation operation.
     *
     * @param self<T> $source
     *
     * @return self<T>
     */
    private static function cloneFrom(self $source): self
    {
        return new self(self::deepClone($source->root));
    }

    /**
     * Performs a deep clone of this node.
     *
     * That is a clone of the node and both its children, recursively.
     *
     * @template U
     *
     * @param Node<U>|null $node
     *
     * @return Node<U>|null
     */
    private static function deepClone(?Node $node): ?Node
    {
        if (null === $node) {
            return null;
        }

        $clone = clone $node;
        $clone->left = self::deepClone($clone->left);
        $clone->right = self::deepClone($clone->right);

        return $clone;
    }
}
