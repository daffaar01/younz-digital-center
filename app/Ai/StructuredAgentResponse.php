<?php

namespace App\Ai;

class StructuredAgentResponse extends AgentResponse
{
    /** @param array<string, mixed> $structured */
    public function __construct(
        public array $structured,
        string $text,
        Usage $usage,
    ) {
        parent::__construct($text, $usage);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->structured;
    }
}
