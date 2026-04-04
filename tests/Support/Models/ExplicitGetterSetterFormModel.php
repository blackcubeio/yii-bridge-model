<?php

declare(strict_types=1);

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
