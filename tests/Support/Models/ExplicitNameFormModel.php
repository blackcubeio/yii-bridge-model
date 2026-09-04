<?php

declare(strict_types=1);

/**
 * ExplicitNameFormModel.php
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
 * FormModel with explicit name in Bridge attribute
 */
class ExplicitNameFormModel extends BridgeFormModel
{
    #[Bridge(name: 'title')]
    public string $formTitle = '';

    public function rules(): array
    {
        return [];
    }

    public function getRules(): array
    {
        return $this->rules();
    }
}
