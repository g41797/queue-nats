<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Cli\LoopInterface;
use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\QueueFactoryInterface;
use Yiisoft\Queue\Nats\Configuration as BrokerConfiguration;

class Adapter implements AdapterInterface
{
    public function __construct(
        private string $channelName = QueueFactoryInterface::DEFAULT_CHANNEL_NAME,
        private ?LoopInterface $loop = null,
        private ?BrokerConfiguration $configuration = null,
        private int $timeout = 3
    ) {
        if (null === $loop ) {
            $loop = new NullLoop();
        }

        if (null === $configuration ) {
            $configuration = BrokerConfiguration::default();
        }
    }
    /**
     * @inheritDoc
     */
    public function runExisting(callable $handlerCallback): void
    {
        // TODO: Implement runExisting() method.
    }

    /**
     * @inheritDoc
     */
    public function status(int|string $id): JobStatus
    {
        // TODO: Implement status() method.
    }

    /**
     * @inheritDoc
     */
    public function push(MessageInterface $message): MessageInterface
    {
        // TODO: Implement push() method.
    }

    /**
     * @inheritDoc
     */
    public function subscribe(callable $handlerCallback): void
    {
        // TODO: Implement subscribe() method.
    }

    public function withChannel(string $channel): AdapterInterface
    {
        if ($channel === $this->channelName) {
            return $this;
        }

        return new self($channel, $this->loop, $this->configuration, $this->timeout);
    }
}
