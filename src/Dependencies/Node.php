<?php

declare(strict_types=1);

namespace App\Dependencies;

use Brick\DateTime\LocalDateTime;

/**
 * @template T
 */
final class Node
{
    public const RED = true;
    public const BLACK = false;

    public bool $color = self::RED;
    public ?LocalDateTime $max = null /* the maximum boundary of this subtree */
    ;

    /**
     * @var self<T>|null
     */
    public ?self $left = null;

    /**
     * @var self<T>|null
     */
    public ?self $right = null;

    /**
     * @param T[] $items
     */
    public function __construct(
        public readonly LocalDateTimeInterval $interval,
        public array $items,
    ) {
        $this->max = $interval->getEnd();
    }

    public function updateMax(): void
    {
        $max = $this->interval->getEnd();

        if (isset($max, $this->left)) {
            $max = null !== $this->left->max ? LocalDateTime::maxOf($max, $this->left->max) : null;
        }

        if (isset($max, $this->right)) {
            $max = null !== $this->right->max ? LocalDateTime::maxOf($max, $this->right->max) : null;
        }

        $this->max = $max;
    }
}
