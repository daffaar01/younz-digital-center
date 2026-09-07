<?php

namespace App\Ai;

class AgentResponse
{
    public function __construct(
        public string $text,
        public Usage $usage,
    ) {}
}
