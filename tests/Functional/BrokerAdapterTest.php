<?php
declare(strict_types=1);

namespace G41797\Queue\Nats\Functional;

use G41797\Queue\Nats\Adapter;
use G41797\Queue\Nats\BrokerFactory;
use G41797\Queue\Nats\BrokerFactoryInterface;
use G41797\Queue\Nats\CheckMessageHandler;
use G41797\Queue\Nats\NullLoop;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Message\IdEnvelope;
use Yiisoft\Queue\Message\Message;
use Yiisoft\Queue\Message\MessageInterface;


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

    private ?NullLoop $loop = null;
    protected function getLoop(): NullLoop
    {
        if ($this->loop == null) {
            $this->loop = new NullLoop();
        }

        return $this->loop;
    }

    protected function createSubmitter() : AdapterInterface {
        $factory = $this->getBrokerFactory();
        $logger = $this->getLogger();
        return new Adapter(logger: $logger);
    }

    protected function createWorker() : AdapterInterface {
        $factory = $this->getBrokerFactory();
        $logger = $this->getLogger();
        $loop = $this->getLoop();
        return new Adapter(logger: $logger, loop: $loop, timeout: 3.0);
    }

    static function getJob(): MessageInterface {
        return new Message("handler", data: 'data',metadata: []);
    }
    public function testSubmitterWorker(): void
    {
        $job = self::getJob();

        $submitter = $this->createSubmitter();
        $this->assertNotNull($submitter);

        $count = 10;

        $submitted = $this->submit($submitter, $job, $count);

        $this->assertEquals($count, count($submitted));

        $worker = $this->createWorker();
        $this->assertNotNull($worker);

        $this->process($worker, $job, $submitted);

        return;
    }

    protected function submit(AdapterInterface $submitter, MessageInterface $job, int $count = 1000): array
    {
        $ids = [];

        for ($i= 0; $i < $count; $i++) {

            $submitted = $submitter->push($job);

            $this->assertNotNull($submitter);
            $this->assertTrue($submitted instanceof IdEnvelope);
            $this->assertArrayHasKey(IdEnvelope::MESSAGE_ID_KEY, $submitted->getMetadata());
            $id = $submitted->getMetadata()[IdEnvelope::MESSAGE_ID_KEY];
            $this->assertIsString($id);

            $ids[] = $id;
        }

        foreach ($ids as $id) {
            $status = $submitter->status($id);
            $this->assertTrue($status->isWaiting());
        }

        return $ids;
    }

    protected function process(AdapterInterface $worker, MessageInterface $expectedJob, array $ids): void
    {
        $this->getLoop()->update(count($ids));
        $this->getHandler()->update($expectedJob);

        $worker->subscribe($this->getCallback());

        $this->assertEquals($this->getHandler()->processed(), count($ids));

        foreach ($ids as $id) {
            $status = $worker->status($id);
            $this->assertTrue($status->isDone());
        }

        return;
    }


}
