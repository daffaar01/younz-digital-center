<?php

namespace App\Data;

final readonly class FirebaseUserIdentity
{
    public function __construct(
        public string $uid,
        public string $email,
        public string $name,
        public ?string $picture,
        public string $provider,
        public bool $emailVerified,
        public ?string $phone = null,
    ) {}
}
