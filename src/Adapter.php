<?php

declare(strict_types=1);

namespace G41797\Queue\Nats;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Cli\LoopInterface;
use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\QueueFactoryInterface;

class Adapter implements AdapterInterface
{
    private BrokerFactoryInterface $brokerFactory;

    public function __construct(
        private string              $channelName = QueueFactoryInterface::DEFAULT_CHANNEL_NAME,
        private array               $brokerConfiguration = [],
        private ?LoggerInterface    $logger = null,
        private ?LoopInterface      $loop = null,
        private float               $timeout = 1.0,
    ) {
        $this->brokerFactory = new BrokerFactory();

        if (null == $loop ) {
            $loop = new NullLoop();
        }

        if (null == $logger) {
            $this->logger = new NullLogger();
        }

        $this->getBroker();
    }

    public function push(MessageInterface $message): MessageInterface
    {
        return $this->broker->push($message);
    }

    public function status(int|string $id): JobStatus
    {

        $jobStatus = $this->broker->jobStatus($id);

        if ($jobStatus == null)
        {
            throw new \InvalidArgumentException('job does not exist');
        }

        return $jobStatus;
    }

    public function runExisting(callable $handlerCallback): void
    {
        $this->processJobs($handlerCallback, continueOnEmptyQueue: false);
    }

    public function subscribe(callable $handlerCallback): void
    {
        $this->processJobs($handlerCallback, continueOnEmptyQueue: true);
    }

    private function processJobs(callable $handlerCallback, bool $continueOnEmptyQueue): void
    {
        $result = true;
        while ($this->loop->canContinue() && $result === true) {
            $job = $this->broker->pull($this->timeout);
            if (null === $job) {
                if ($continueOnEmptyQueue)
                {
                    continue;
                }
                break;
            }
            $result = $handlerCallback($job);
            $this->broker->done($job->getId());
        }
    }

    public function withChannel(string $channel): AdapterInterface
    {
        if ($channel == $this->channelName) {
            return $this;
        }

        return new self(
                            $this->brokerFactory,
                            $this->channelName,
                            $this->brokerConfiguration,
                            $this->logger,
                            $this->loop
                        );
    }

    private ?BrokerInterface $broker = null;

    private function getBroker(): ?BrokerInterface
    {
        if ($this->broker == null) {
            $this->broker = $this->brokerFactory->get
                                    (
                                        $this->channelName,
                                        $this->brokerConfiguration,
                                        $this->logger
                                    );
        }
        return $this->broker;
    }
}
