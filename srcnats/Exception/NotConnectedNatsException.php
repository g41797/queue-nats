<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats\Exception;

use Yiisoft\FriendlyException\FriendlyExceptionInterface;

class NotConnectedNatsException extends \RuntimeException implements FriendlyExceptionInterface
{
    public function getName(): string
    {
        return 'Not connected to Nats.';
    }

    public function getSolution(): ?string
    {
        return 'Check your Nats configuration and run nats->connect() before using it.';
    }
}

