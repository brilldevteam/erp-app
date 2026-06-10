<?php

namespace App\Data;

class SocialProviderUser
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $avatar,
        public readonly bool $emailTrusted,
    ) {
    }
}
