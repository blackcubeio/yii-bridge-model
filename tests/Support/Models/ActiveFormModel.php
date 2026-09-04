<?php

declare(strict_types=1);

/**
 * ActiveFormModel.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Tests\Support\Models;

use Blackcube\BridgeModel\Attributes\Bridge;
use Blackcube\BridgeModel\BridgeFormModel;

/**
 * FormModel with isActive() and setActive() separately bridged.
 * This is the case that was broken: second setEndpoint() was overwriting first.
 */
class ActiveFormModel extends BridgeFormModel
{
    private bool $active = false;

    #[Bridge]
    public function isActive(): bool
    {
        return $this->active;
    }

    #[Bridge]
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
