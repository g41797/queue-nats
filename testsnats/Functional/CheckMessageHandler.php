<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats\Functional;

use Yiisoft\Queue\Message\Message;
use Yiisoft\Queue\Message\MessageInterface;

class CheckMessageHandler
{
    private MessageInterface $expected;
    public function __construct(MessageInterface $msg)
    {
        $this->update($msg);
    }

    public function update(MessageInterface $msg): void
    {
        $this->expected = new Message   (
            $msg->getHandlerName(),
            $msg->getMessage(),
            $msg->getMetadata()
        );
    }

    private int $jobs = 0;

    public function reset(): int
    {
        $result = $this->jobs;
        $this->jobs = 0;
        return $result;
    }

    public function handle(MessageInterface $message): bool
    {
        $this->jobs =+ 1;

        return $message == $this->expected;
    }


}
