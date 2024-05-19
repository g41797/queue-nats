<?php

declare(strict_types=1);

use Yiisoft\Queue\Adapter\AdapterInterface;
use Yiisoft\Queue\Nats\Adapter;
use Yiisoft\Queue\Nats\BrokerFactoryInterface;
use Yiisoft\Queue\Nats\BrokerFactory;

// TODO: how to proceed
// https://github.com/yiisoft/queue/blob/master/config/di.php
// https://github.com/yiisoft/yii2-queue/blob/master/docs/guide/driver-amqp-interop.md
// https://github.com/yiisoft/definitions
// https://github.com/yiisoft/factory

return [
    Yiisoft\Queue\Adapter\AdapterInterface::class => Yiisoft\Queue\Nats\Adapter::class,
    Yiisoft\Queue\Nats\BrokerFactoryInterface::class => Yiisoft\Queue\Nats\BrokerFactory::class,
];
