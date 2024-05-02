<?php

namespace Yiisoft\Queue\Nats\Message;

use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\Nats\Exception\DelayNotImplementedNatsException;

class Message implements MessageInterface
{
    public function __construct(
        private string $handlerName,
        private mixed $data,
        private array $metadata,
        private int $delay = 0 //delay in seconds
    ) {
        if (0 != $delay) {
            throw new DelayNotImplementedNatsException();
        }
    }

    public function withDelay(int $delay): self
    {
        if (0 != $delay) {
            throw new DelayNotImplementedNatsException();
        }

        $message = clone $this;

        return $message;
    }

    public function getHandlerName(): string
    {
        return $this->handlerName;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
