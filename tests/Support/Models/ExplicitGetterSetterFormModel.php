<?php

declare(strict_types=1);

/**
 * ExplicitGetterSetterFormModel.php
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
 * FormModel with explicit getter/setter in Bridge attribute
 */
class ExplicitGetterSetterFormModel extends BridgeFormModel
{
    #[Bridge(getter: 'getEmail', setter: 'setEmail')]
    public string $contactEmail = '';

    public function rules(): array
    {
        return [];
    }

    public function getRules(): array
    {
        return $this->rules();
    }
}
