<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Psr\Log\LoggerInterface;
use Yiisoft\Queue\Nats\Configuration as BrokerConfiguration;
use Yiisoft\Queue\QueueFactoryInterface;

class BrokerFactory implements BrokerFactoryInterface
{

    public function get(
                            string $channel = QueueFactoryInterface::DEFAULT_CHANNEL_NAME,
                            array $config = [],
                            ?LoggerInterface $logger = null
                        ): ?BrokerInterface {
        return new Broker(
            $channel,
             BrokerConfiguration::default()->update($config),
            $logger
        );
    }
}
