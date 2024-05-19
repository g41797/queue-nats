<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Yiisoft\Queue\Cli\LoopInterface;

class NullLoop implements LoopInterface
{
    private bool $forever = true;

    private int $rest = 0;

    public function __construct(int $loops = -1)
    {
        if ($loops >= 0) {
            $this->forever = false;
            $this->rest = $loops;
        }
    }

    /**
     * @inheritDoc
     */
    public function canContinue(): bool
    {
        if ($this->forever) {
            return true;
        }

        if ($this->rest === 0) {
            return false;
        }

        $this->rest -= 1;
        return true;
    }
}
