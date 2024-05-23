<?php

declare(strict_types=1);

namespace G41797\Queue\Nats;

use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\Message\IdEnvelope;

interface BrokerInterface
{
    public function withChannel(string $channel): self;

    public function push(MessageInterface $job): ?IdEnvelope;

    public function jobStatus(string $id): ?JobStatus;

    public function pull(float $timeout): ?IdEnvelope;

    public function done(string $id): bool;
}
