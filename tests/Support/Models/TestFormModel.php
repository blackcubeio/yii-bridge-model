<?php

declare(strict_types=1);

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
