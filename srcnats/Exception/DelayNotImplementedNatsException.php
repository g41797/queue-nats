<?php

namespace Yiisoft\Queue\Nats\Exception;

use Yiisoft\FriendlyException\FriendlyExceptionInterface;

class DelayNotImplementedNatsException extends \RuntimeException implements FriendlyExceptionInterface
{
    public function getName(): string
    {
        return 'Delay not implemented';
    }

    public function getSolution(): ?string
    {
        return 'Use 0 as delay value';
    }
}
