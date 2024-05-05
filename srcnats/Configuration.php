<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

final class Configuration
{
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 4222
    ) {
    }

    static public function default(): self {
        return new self();
    }
}
