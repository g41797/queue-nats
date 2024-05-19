<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

final class Configuration
{
    public function __construct(
        public string   $host = 'localhost',
        public int      $port = 4222
    ) {
    }

    public function update(array $config): self
    {
        if (array_key_exists('host', $config))
        {
            $this->host = $config['host'];
        }

        if (array_key_exists('port', $config))
        {
            $this->port = $config['port'];
        }

        return $this;
    }

    static public function default(): self {
        return new self();
    }
}
