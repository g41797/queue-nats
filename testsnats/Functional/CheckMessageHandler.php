<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats\Functional;

use Yiisoft\Queue\Message\Message;
use Yiisoft\Queue\Message\MessageInterface;

class CheckMessageHandler
{
    private ?MessageInterface $expected = null;
    public function __construct(?MessageInterface $msg = null)
    {
        $this->update($msg);
    }

    public function update(?MessageInterface $msg): self
    {
        if ($msg !== null) {
            $this->expected = new Message   (
                $msg->getHandlerName(),
                $msg->getMessage(),
                $msg->getMetadata()
            );
        }
        return $this;
    }

    private int $jobs = 0;

    public function reset(): self
    {
        $this->jobs = 0;
        return $this;
    }

    public function handle(MessageInterface $message): bool
    {
        $this->jobs =+ 1;

        return $message == $this->expected;
    }


}
