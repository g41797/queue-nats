<?php

declare(strict_types=1);

namespace G41797\Queue\Nats\Functional;

use Ramsey\Uuid\Uuid;
use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\Message;
use G41797\Queue\Nats\Broker;

class BrokerTest extends FunctionalTestCase
{
    public function testSetUp(): void
    {
        $this->assertTrue(true);
        return;
    }

    public function testConnectDisconnect(): void
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());
        $broker->disconnect();
        $this->assertFalse($broker->isConnected());
        return;
    }

    public function testGetDeleteSubmitted(): void
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());

        $this->assertNotNull($broker->getSubmitted());

        $this->assertTrue(in_array($broker->streamName, $this->getStreamNames()));

        $broker->deleteSubmitted();

        $this->assertFalse(in_array($broker->streamName, $this->getStreamNames()));

        return;
    }
    public function testGetTwoSubmitted(): void
    {
        $broker1 = new Broker();
        $this->assertTrue($broker1->isConnected());
        $this->assertNotNull($broker1->getSubmitted());
        $this->assertTrue(in_array($broker1->streamName, $this->getStreamNames()));

        $broker2 = new Broker();
        $this->assertTrue($broker2->isConnected());
        $this->assertNotNull($broker2->getSubmitted());
        $this->assertTrue(in_array($broker2->streamName, $this->getStreamNames()));

        $broker1->deleteSubmitted();
        $this->assertFalse(in_array($broker1->streamName, $this->getStreamNames()));
        $this->assertFalse(in_array($broker2->streamName, $this->getStreamNames()));

        return;
    }
    public function testGetDeleteStatuses(): void
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());

        $this->assertNotNull($broker->getStatuses());

        $this->assertTrue(in_array(Broker::bucketStreamName($broker->bucketName), $this->getStreamNames()));

        $broker->deleteStatuses();

        $this->assertFalse(in_array(Broker::bucketStreamName($broker->bucketName), $this->getStreamNames()));

        return;
    }

    public function testGetTwoStatuses(): void
    {
        $broker1 = new Broker();
        $this->assertTrue($broker1->isConnected());
        $this->assertNotNull($broker1->getStatuses());
        $this->assertTrue(in_array(Broker::bucketStreamName($broker1->bucketName), $this->getStreamNames()));

        $broker2 = new Broker();
        $this->assertTrue($broker2->isConnected());
        $this->assertNotNull($broker2->getStatuses());
        $this->assertTrue(in_array(Broker::bucketStreamName($broker2->bucketName), $this->getStreamNames()));

        $broker1->deleteStatuses();
        $this->assertFalse(in_array(Broker::bucketStreamName($broker1->bucketName), $this->getStreamNames()));
        $this->assertFalse(in_array(Broker::bucketStreamName($broker2->bucketName), $this->getStreamNames()));

        return;
    }

    public function testTwoBrokersReady(): void
    {
        $broker1 = new Broker();
        $this->assertTrue($broker1->isReady());

        $broker2 = new Broker();
        $this->assertTrue($broker2->isReady());

        return;
    }

    public function testSubmitGetStatus(): void
    {
        $submitter = new Broker();
        $this->assertTrue($submitter->isReady());

        $job = new Message('jobhandler', 'jobdata');

        $extjob = $submitter->push($job);
        $this->assertNotNull($extjob);

        $jobStatus = $submitter->jobStatus($extjob->getId());
        $this->assertNotNull($jobStatus);
        $this->assertTrue($jobStatus->isWaiting());

        return;
    }

    public function testSubmitProcessStatus(): void
    {
        $submitter = new Broker();
        $this->assertTrue($submitter->isReady());

        $job = new Message('jobhandler', 'jobdata');

        $extjob = $submitter->push($job);
        $this->assertNotNull($extjob);

        $jobStatus = $submitter->jobStatus($extjob->getId());
        $this->assertNotNull($jobStatus);
        $this->assertTrue($jobStatus->isWaiting());

        $worker = new Broker();
        $this->assertTrue($worker->isReady());
        $this->assertTrue($worker->done($extjob->getId()));

        $jobStatus = $submitter->jobStatus($extjob->getId());
        $this->assertNotNull($jobStatus);
        $this->assertTrue($jobStatus->isDone());

        return;
    }

    public function testCreateConsumer(): void
    {
        $broker = new Broker();
        $this->assertTrue($broker->isReadyToConsume());

        return;
    }
    public function testSubmitInProcessStatus(): void
    {
        // Start worker
        $worker = new Broker();
        $this->assertTrue($worker->isReadyToConsume());

        // Consume first job
        $recvjob = $worker->pull(1.0);

        // Nothing to process
        $this->assertNull($recvjob);

        // Submit job
        $submitter = new Broker();
        $this->assertTrue($submitter->isReady());

        $job = new Message('jobhandler', 'jobdata');

        $extjob = $submitter->push($job);
        $this->assertNotNull($extjob);

        // Processing is not started, job status "WAITING"
        $jobStatus = $submitter->jobStatus($extjob->getId());
        $this->assertNotNull($jobStatus);
        $this->assertTrue($jobStatus->isWaiting());

        // Consume first job
        $recvjob = $worker->pull(5.0);
        $this->assertNotNull($recvjob);
        $this->assertEquals($recvjob->getId(), $extjob->getId());
        $this->assertEquals($recvjob->getHandlerName(), $extjob->getHandlerName());
        $this->assertEquals($recvjob->getData(), $extjob->getData());

        // In process, job status "RESERVED"
        $jobStatus = $submitter->jobStatus($extjob->getId());
        $this->assertNotNull($jobStatus);
        $this->assertTrue($jobStatus->isReserved());

        // Simulate job finished by worker
        $this->assertTrue($worker->done($extjob->getId()));

        // Job status "DONE"
        $jobStatus = $submitter->jobStatus($extjob->getId());
        $this->assertNotNull($jobStatus);
        $this->assertTrue($jobStatus->isDone());

        return;
    }

    public function testPutToSubmitted(): void
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());
        $this->assertNotNull($broker->getSubmitted());
        $this->assertTrue(in_array($broker->streamName, $this->getStreamNames()));

        $count = 1000;
        $puttime = self::getTimeOfPutToSubmitted($broker, $count);

        return;
    }

    public function testPutToStatuses(): void
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());
        $this->assertNotNull($broker->getSubmitted());
        $this->assertTrue(in_array($broker->streamName, $this->getStreamNames()));
        $this->assertNotNull($broker->getStatuses());

        $count = 1000;
        $puttime = self::getTimeOfPutToStatuses($broker, $count);

        return;
    }


    static public function getTimeOfPutToSubmitted(Broker $broker, int $count = 1): float
    {
        $payload = $broker->serializer->serialize(BrokerAdapterTest::getJob());

        $start = microtime(true);

        for ($i = 0; $i < $count; $i++)
        {
            // $uuid = Uuid::uuid7()->toString();
            $broker->submitted->put(sprintf('%s.%s', $broker->streamName, $i), $payload);
        }

        $dt = number_format((microtime(true) - $start) * 1000, 2);;

        return  $dt/$count;
    }

    static public function getTimeOfPutToStatuses(Broker $broker, int $count = 1): float
    {
        $start = microtime(true);

        for ($i = 0; $i < $count; $i++)
        {
            //$uuid = Uuid::uuid7()->toString();
            $broker->statuses->put(strval($i), $broker->statusString[JobStatus::WAITING]);
        }

        $dt = number_format((microtime(true) - $start) * 1000, 2);;

        return  $dt/$count;
    }

}
