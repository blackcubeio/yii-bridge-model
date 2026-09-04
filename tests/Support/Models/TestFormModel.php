<?php

declare(strict_types=1);

/**
 * TestFormModel.php
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
 * Test FormModel for testing BridgeFormModel
 */
class TestFormModel extends BridgeFormModel
{
    #[Bridge]
    public string $name = '';

    #[Bridge]
    public ?int $age = null;

    #[Bridge]
    public bool $active = false;

    #[Bridge]
    public string $email = '';

    public function rules(): array
    {
        return [];
    }

    public function getRules(): array
    {
        return $this->rules();
    }
}
