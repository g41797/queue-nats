<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats\Functional;

use Yiisoft\Queue\Message\Message;
use Yiisoft\Queue\Nats\Broker;

class BrokerTest extends FunctionalTestCase
{
    public function testConnectDisconnect()
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());
        $broker->disconnect();
        $this->assertFalse($broker->isConnected());
        return;
    }

    public function testGetDeleteSubmitted()
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());

        $this->assertNotNull($broker->getSubmitted());

        $this->assertTrue(in_array($broker->streamName, $this->getStreamNames()));

        $broker->deleteSubmitted();

        $this->assertFalse(in_array($broker->streamName, $this->getStreamNames()));

        return;
    }
    public function testGetTwoSubmitted()
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
    public function testGetDeleteStatuses()
    {
        $broker = new Broker();
        $this->assertTrue($broker->isConnected());

        $this->assertNotNull($broker->getStatuses());

        $this->assertTrue(in_array(Broker::bucketStreamName($broker->bucketName), $this->getStreamNames()));

        $broker->deleteStatuses();

        $this->assertFalse(in_array(Broker::bucketStreamName($broker->bucketName), $this->getStreamNames()));

        return;
    }

    public function testGetTwoStatuses()
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

    public function testTwoBrokersReady()
    {
        $broker1 = new Broker();
        $this->assertTrue($broker1->isReady());

        $broker2 = new Broker();
        $this->assertTrue($broker2->isReady());

        return;
    }

    public function testSubmitGetStatus()
    {
        $submitter = new Broker();
        $this->assertTrue($submitter->isReady());

        $job = new Message('jobhandler', 'jobdata');

        $extjob = $submitter->push($job);
        $this->assertNotNull($extjob);

        $jobStatus = $submitter->jobStatus($extjob);
        $this->assertNotNull($jobStatus);
        $this->assertTrue($jobStatus->isWaiting());

        return;
    }


}
