<?php

declare(strict_types=1);

namespace G41797\Queue\Nats;

use Yiisoft\Queue\Message\IdEnvelope;
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
            $expected = new Message(
                $msg->getHandlerName(),
                $msg->getData(),
                $msg->getMetadata()
            );
            $this->expected = $expected;
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
        $this->jobs += 1;

        if ($message instanceof IdEnvelope) {
            $message = $message->getMessage();
        }

        $eh = ($message->getHandlerName() == $this->expected->getHandlerName());
        $ed = ($message->getData() == $this->expected->getData());
        $em = ($message->getMetadata() == $this->expected->getMetadata());

        return ($eh && $ed && $em);
    }

    public function processed(): int
    {
        return $this->jobs;
    }
}
