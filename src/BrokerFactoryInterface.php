<?php

declare(strict_types=1);

namespace G41797\Queue\Nats;

use Psr\Log\LoggerInterface;
use Yiisoft\Queue\QueueFactoryInterface;

interface BrokerFactoryInterface
{
    public function get(
            string $channel = QueueFactoryInterface::DEFAULT_CHANNEL_NAME,
            array $config = [],
            ?LoggerInterface $logger = null
        ): ?BrokerInterface;
}
