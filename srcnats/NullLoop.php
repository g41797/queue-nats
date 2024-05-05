<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Yiisoft\Queue\Cli\LoopInterface;

class NullLoop implements LoopInterface
{
    /**
     * @inheritDoc
     */
    public function canContinue(): bool
    {
        return true;
    }
}
