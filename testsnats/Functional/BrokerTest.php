<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats\Functional;

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

        $broker->getSubmitted()->delete();

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

        $broker1->getSubmitted()->delete();
        $this->assertFalse(in_array($broker1->streamName, $this->getStreamNames()));
        $this->assertFalse(in_array($broker2->streamName, $this->getStreamNames()));

        return;
    }



}
