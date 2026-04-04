<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Models;

/**
 * Target object with isActive() and setActive() for bridging.
 */
class ActiveTarget
{
    private bool $active = false;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
