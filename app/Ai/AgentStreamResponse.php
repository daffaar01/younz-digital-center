<?php

namespace App\Ai;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, TextDelta> */
class AgentStreamResponse extends AgentResponse implements IteratorAggregate
{
    /** @param array<int, TextDelta> $deltas */
    public function __construct(string $text, Usage $usage, private readonly array $deltas)
    {
        parent::__construct($text, $usage);
    }

    /** @return Traversable<int, TextDelta> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->deltas);
    }
}
