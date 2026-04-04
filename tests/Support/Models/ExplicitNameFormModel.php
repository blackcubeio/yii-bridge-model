<?php

declare(strict_types=1);

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
