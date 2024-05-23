<?php

declare(strict_types=1);

namespace G41797\Queue\Nats;

use Yiisoft\Queue\Cli\LoopInterface;

class NullLoop implements LoopInterface
{
    private bool $forever = true;

    private int $initial = 0;
    private int $rest = 0;

    public function __construct(int $loops = -1)
    {
        $this->update($loops);
    }

    public function update(int $loops = -1): void
    {
        $this->forever = ($loops < 0);

        if (!$this->forever) {
            $this->initial = $loops;
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
            $this->update($this->initial);
            return false;
        }

        $this->rest -= 1;
        return true;
    }
}
