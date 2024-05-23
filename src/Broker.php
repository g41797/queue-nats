<?php

declare(strict_types=1);

namespace G41797\Queue\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration as NatsConfiguration;
use Basis\Nats\Connection;
use Basis\Nats\Queue;
use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\Configuration as ConsumerConfiguration;
use Basis\Nats\Consumer\Consumer;
use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Consumer\ReplayPolicy;
use Basis\Nats\Message\Payload as Nats;
use Basis\Nats\Stream\DiscardPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Basis\Nats\Stream\Stream;
use Basis\Nats\KeyValue\Bucket;

use Ramsey\Uuid\Uuid;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\IdEnvelope;
use Yiisoft\Queue\Message\Message As YiiMessage;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\Message\JsonMessageSerializer;
use Yiisoft\Queue\QueueFactoryInterface;

use G41797\Queue\Nats\Configuration as BrokerConfiguration;


class Broker implements BrokerInterface
{
    public string $streamName;
    private string $subject;
    private string $prefix;
    public string $bucketName;
    private bool $returnDisconnected = false;

    public array $statusString =
        [
            JobStatus::WAITING => 'WAITING',
            JobStatus::RESERVED => 'RESERVED',
            JobStatus::DONE => 'DONE'
        ];

    public JsonMessageSerializer $serializer;

    public function __construct(
        private string                  $channelName = QueueFactoryInterface::DEFAULT_CHANNEL_NAME,
        private ?BrokerConfiguration    $configuration = null,
        private ?LoggerInterface        $logger = null
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

        $this->streamName = strtoupper($this->channelName) . "JOBS";
        $this->subject = $this->streamName . ".*";
        $this->prefix = $this->streamName . ".";
        $this->bucketName = $this->streamName;

        $this->serializer = new JsonMessageSerializer();
    }


    public function withChannel(string $channel): BrokerInterface
    {
        if ($channel == $this->channelName) {
            return $this;
        }

        return new self($channel, $this->configuration, $this->logger);
    }

    public function push(MessageInterface $job): ?IdEnvelope
    {
        if (!$this->isReady()) {
            return null;
        }

        $uuid = Uuid::uuid7()->toString();
        $payload = $this->serializer->serialize($job);

        $this->statuses->put($uuid, $this->statusString[JobStatus::WAITING]);
        $this->submitted->put(sprintf('%s.%s', $this->streamName, $uuid), $payload);

        return new IdEnvelope($job, $uuid);
    }

    public function jobStatus(string $id): ?JobStatus
    {
        if (empty($id))
        {
            return null;
        }

        if (!$this->isReady()) {
            return null;
        }

        try {
            return self::stringToJobStatus($this->statuses->get($id));
        } catch (\Exception) {
            return null;
        }
    }

    public function pull(float $timeout): ?IdEnvelope
    {
        if (!$this->isReadyToConsume()) {
            return null;
        }

        try {
            $msg = $this->queue->next($timeout);
        }
        catch (\Exception) {
            return null;
        }

        if (null == $msg) {
            return null;
        }

        $payload = $msg->payload;

        // We don't use headers
        // NATS uses headers for errors
        if ($payload->hasHeaders()) {
            return null;
        }

        $mi = $this->serializer->unserialize($payload->body);

        $id = str_replace($this->prefix, "", $msg->payload->subject);

        $this->statuses->put($id, $this->statusString[JobStatus::RESERVED]);

        $msg->ack();

        return new IdEnvelope($mi, $id);
    }

    public function done(string $id): bool
    {
        if (empty($id))
        {
            return false;
        }

        if (!$this->isReady()) {
            return false;
        }

        $this->statuses->put($id, $this->statusString[JobStatus::DONE]);
        return true;
    }

    public ?Stream $submitted = null;

    public function getSubmitted(): ?Stream
    {
        if ($this->submitted !== null) {
            return $this->submitted;
        }

        $stream = $this->
                    getClient()->
                    getApi()->
                    getStream($this->streamName);

        $stream->
            getConfiguration()->
            setSubjects([$this->subject])->
            setRetentionPolicy(RetentionPolicy::WORK_QUEUE)->
            setStorageBackend(StorageBackend::FILE)->
            setDiscardPolicy(DiscardPolicy::OLD);

        $stream->create();

        if (!$stream->exists()) {
            $this->logger->error("can not create jetstream " . $this->streamName . " for submitting jobs");
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

    public ?Bucket $statuses = null;

    public function getStatuses(): ?Bucket
    {
        if ($this->statuses !== null) {
            return $this->statuses;
        }

        $bucket = $this->
            getClient()->
            getApi()->
            getBucket($this->bucketName);

        if (!$bucket->getStream()->exists()) {
            $this->logger->error("can not create kv storage " . $this->bucketName . " for statuses");
            return null;
        }

        $this->statuses = $bucket;

        return $this->statuses;
    }

    public function deleteStatuses(): void
    {
        if ($this->statuses == null) {
            return;
        }

        if (!$this->statuses->getStream()->exists()) {
            $this->statuses = null;
            return;
        }

        $this->statuses->getStream()->delete();
        $this->statuses = null;
        return;
    }

    public function isReadyToConsume(): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        return null !== $this->getConsumer();
    }

    public ?Consumer   $consumer = null;
    public ?Queue      $queue      = null;

    public function getConsumer(): ?Consumer
    {
        if ($this->consumer !== null) {
            return $this->consumer;
        }

        $consumer = $this->submitted->getConsumer($this->streamName);
        $consumer->setBatching(1);
        $consumer->
            getConfiguration()->
                setAckPolicy(AckPolicy::EXPLICIT)->
                setName($this->streamName)->
                setSubjectFilter($this->subject)->
                setDeliverPolicy(DeliverPolicy::ALL)->
                setMaxAckPending(1);

        $consumer->create();

        if (!$consumer->exists())
        {
            $this->logger->error("can not create consumer for stream " . $this->streamName);
            return null;
        }

        $this->queue = $consumer->getQueue();
        $this->consumer = $consumer;
        return $this->consumer;
    }
    public function deleteConsumer()
    {
        if ($this->consumer !== null) {
            $this->consumer->delete();
            $this->consumer = null;
        }
    }

    protected ?Client $client = null;

    private function getClient(): Client
    {
        return $this->client ?:
                $this->client = new Client($this->natsConfiguration()->setDelay(0.1));
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

    public function isReady(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        if (null == $this->getSubmitted()) {
            return false;
        }

        if (null == $this->getStatuses()) {
            return false;
        }

        return true;
    }

    static public function bucketStreamName(string $name): string
    {
        return strtoupper("kv_$name");
    }

    static public function stringToJobStatus(string $status): ?JobStatus
    {
        return match ($status) {
            'WAITING' => JobStatus::waiting(),
            'RESERVED' => JobStatus::reserved(),
            'DONE' => JobStatus::done(),
            default => null,
        };
    }

}
