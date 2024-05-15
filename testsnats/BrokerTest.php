<?php

namespace Yiisoft\Queue\Nats\Functional;

use Yiisoft\Queue\Nats\Broker;
use PHPUnit\Framework\TestCase;

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
}
