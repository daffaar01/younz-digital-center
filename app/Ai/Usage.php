<?php

namespace App\Ai;

class Usage
{
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
    ) {}
}
