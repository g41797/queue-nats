<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Yiisoft\Queue\Enum\JobStatus;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\Message\IdEnvelope;

interface BrokerInterface
{
    public function withChannel(string $channel): self;

    public function push(MessageInterface $job): ?IdEnvelope;

    public function jobStatus(IdEnvelope $job): ?JobStatus;

    public function pull(float $timeout): ?IdEnvelope;

    public function done(IdEnvelope $job): bool;
}
