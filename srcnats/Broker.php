<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Basis\Nats\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Basis\Nats\Client;
use Basis\Nats\Configuration as NatsConfiguration;

use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\IdEnvelope;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\QueueFactoryInterface;

class Broker implements BrokerInterface
{
    private string $streamName;
    private string $subject;
    private string $bucket;

    public function __construct(
        private string $channelName = QueueFactoryInterface::DEFAULT_CHANNEL_NAME,
        private ?Configuration $configuration = null,
        private ?LoggerInterface $logger = null
    ) {
        if (null === $configuration ) {
            $configuration = Configuration::default();
        }
        if (null === $logger ) {
            $logger = new NullLogger();
        }

        if (empty($this->channelName))
        {
            $this->channelName = QueueFactoryInterface::DEFAULT_CHANNEL_NAME;
        }

        $this->streamName   = strtoupper($this->channelName) . "JOBS";
        $this->subject      = $this->streamName . ".*";
        $this->bucket       = $this->streamName . "-jobstates";
    }


    public function withChannel(string $channel): BrokerInterface
    {
        if ($channel === $this->channelName) {
            return $this;
        }

        return new self($channel, $this->configuration, $this->logger);
    }

    public function push(MessageInterface $job): ?IdEnvelope
    {
        if (!$this->isConnected())
        {
            return null;
        }

        return null;
    }

    public function jobStatus(IdEnvelope $job): ?JobStatus
    {
        return null;
    }

    public function pull(float $timeout): ?IdEnvelope
    {
        return null;
    }

    public function notify(IdEnvelope $job, JobStatus $jobStatus): bool
    {
        return false;
    }

    protected ?Client $client = null;

    private function getClient(): Client
    {
        return $this->client ?: $this->client = new Client($this->natsConfiguration());
    }

    private function natsConfiguration(): NatsConfiguration
    {
        return new NatsConfiguration([
            'scheme' => 'nats',
            'host' => $this->configuration->host,
            'port' => $this->configuration->port,
            'user' => null,
            'pass' => null,
            'pedantic' => false,
            'reconnect' => true,
        ]);
    }

    public function isConnected(): bool
    {
        if ($this->getClient()->ping()) {
            return true;
        }

        $this->logger->error("nats broker is not connected");
        return false;
    }

    public function disconnect(): void
    {

        if (null == $this->client){
            return;
        }

        // nats client does not support explicit close api
        // if connection still exists, internal socket handle is closed directly
        $property = new \ReflectionProperty(Connection::class, 'socket');
        fclose($property->getValue($this->client->connection));

        return;
    }


}
