<?php

namespace App\Ai;

class AgentPrompt
{
    /** @param array<int, mixed> $attachments */
    public function __construct(
        public string $prompt,
        public array $attachments,
        public ?string $provider,
        public ?string $model,
        public ?int $timeout,
    ) {}
}
