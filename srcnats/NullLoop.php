<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats;

use Yiisoft\Queue\Cli\LoopInterface;

class NullLoop implements LoopInterface
{
    private bool $forever = true;

    private int $initisl = 0;
    private int $rest = 0;

    public function __construct(int $loops = -1)
    {
        $this->update($loops);
    }

    public function update(int $loops = -1): void
    {
        $this->forever = ($loops < 0);

        if (!$this->forever) {
            $this->initisl = $loops;
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
            $this->update($this->initisl);
            return false;
        }

        $this->rest -= 1;
        return true;
    }
}
