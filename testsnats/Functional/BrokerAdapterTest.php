<?php
declare(strict_types=1);

namespace Yiisoft\Queue\Nats\Functional;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Yiisoft\Queue\Nats\Adapter;
use Yiisoft\Queue\Nats\BrokerFactory;
use Yiisoft\Queue\Nats\BrokerFactoryInterface;

use Yiisoft\Queue\Message\Message;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\Message\IdEnvelope;

use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Cli\LoopInterface;
use Yiisoft\Queue\Enum\JobStatus;

use Yiisoft\Queue\QueueFactoryInterface;


class BrokerAdapterTest extends FunctionalTestCase
{

    private ?BrokerFactoryInterface $brokerFactory = null;

    private function getBrokerFactory(): BrokerFactoryInterface
    {
        if ($this->brokerFactory == null) {
            $this->brokerFactory = new BrokerFactory();
        }
        return $this->brokerFactory;
    }

    private ?LoggerInterface $logger = null;
    private function getLogger(): LoggerInterface
    {
        if ($this->logger == null) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }

    private ?CheckMessageHandler $handler = null;

    protected function getCallback(): callable
    {
        return [$this->getHandler(), 'handle'];
    }

    protected function getHandler(): CheckMessageHandler
    {
        if ($this->handler == null) {
            $this->handler = new CheckMessageHandler();
        }

        return $this->handler;
    }

    protected function createSubmitter() : AdapterInterface {
        $factory = $this->getBrokerFactory();
        $logger = $this->getLogger();
        $adapter = new Adapter($factory, logger: $logger);
        return $adapter;
    }

    public function testSubmitter(): void
    {
        $job = new Message("handler", data: 'data',metadata: []);

        $submitter = $this->createSubmitter();
        $this->assertNotNull($submitter);

        $this->submit($submitter,$job);

        return;
    }

    protected function submit(AdapterInterface $adapter, MessageInterface $job, int $count = 1000): void
    {
        for ($i= 0; $i < $count; $i++) {

            $submitted = $adapter->push($job);

            $this->assertNotNull($adapter);
            $this->assertTrue($submitted instanceof IdEnvelope);
            $this->assertArrayHasKey(IdEnvelope::MESSAGE_ID_KEY, $submitted->getMetadata());
            $id = $submitted->getMetadata()[IdEnvelope::MESSAGE_ID_KEY];
            $this->assertIsString($id);

            $status = $adapter->status($id);
            $this->assertTrue($status->isWaiting());
        }
    }

}
