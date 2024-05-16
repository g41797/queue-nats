<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration as NatsConfiguration;
use Basis\Nats\Connection;
use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\Configuration;
use Basis\Nats\Consumer\Consumer;
use Basis\Nats\Consumer\ReplayPolicy;
use Basis\Nats\Message\Payload;
use Basis\Nats\Stream\DiscardPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Basis\Nats\Stream\Stream;
use Basis\Nats\KeyValue\Bucket;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\IdEnvelope;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\QueueFactoryInterface;
use Yiisoft\Queue\Nats\Configuration as BrokerConfiguration;


class Broker implements BrokerInterface
{
    public string $streamName;
    private string $subject;
    public string $bucketName;
    private bool $returnDisconnected = false;
    private ?Bucket $statuses = null;


    public function __construct(
        private string $channelName = QueueFactoryInterface::DEFAULT_CHANNEL_NAME,
        private ?BrokerConfiguration $configuration = null,
        private ?LoggerInterface $logger = null
    ) {
        if (null == $configuration) {
            $this->configuration = BrokerConfiguration::default();
        }
        if (null == $logger) {
            $this->logger = new NullLogger();
        }

        if (empty($this->channelName)) {
            $this->channelName = QueueFactoryInterface::DEFAULT_CHANNEL_NAME;
        }

        $this->streamName   = strtoupper($this->channelName) . "JOBS";
        $this->subject      = $this->streamName . ".*";
        $this->bucketName   = $this->streamName . "-jobstates";
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
        if (!$this->isConnected()) {
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

    private ?Stream $submitted = null;

    public function getSubmitted(): ?Stream
    {
        if ($this->submitted !== null) {
            return $this->submitted;
        }

        $stream = $this->getClient()->getApi()->getStream($this->streamName);
        $stream->getConfiguration()->
            setSubjects([$this->subject])->
            setRetentionPolicy(RetentionPolicy::WORK_QUEUE)->
            setStorageBackend(StorageBackend::FILE)->
            setDiscardPolicy(DiscardPolicy::OLD);

        $stream->create();

        if (!$stream->exists()) {
            $this->logger->error("can not create jetstream ".$this->streamName." for submitting jobs");
            return null;
        }

        $this->submitted = $stream;
        return $this->submitted;
    }

    public function deleteSubmitted(): void
    {
        if ($this->submitted == null) {
            return;
        }

        if (!$this->submitted->exists()) {
            $this->submitted = null;
            return;
        }

        $this->submitted->delete();
        $this->submitted = null;
        return;
    }

    private function natsConfiguration(): NatsConfiguration
    {
        return new NatsConfiguration([
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
        if ($this->returnDisconnected) {
            return false;
        }

        if ($this->getClient()->ping()) {
            return true;
        }

        $this->logger->error('nats broker is not connected');
        return false;
    }

    public function disconnect(): void
    {
        $this->returnDisconnected = true;

        if (null == $this->client) {
            return;
        }

        // nats client does not support explicit close api
        // if connection still exists, internal socket handle is closed directly
        $property = new \ReflectionProperty(Connection::class, 'socket');
        fclose($property->getValue($this->client->connection));

        return;
    }


}
