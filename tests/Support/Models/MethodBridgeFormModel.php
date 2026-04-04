<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Models;

use Blackcube\BridgeModel\Attributes\Bridge;
use Blackcube\BridgeModel\BridgeFormModel;

/**
 * FormModel with bridged methods (getter source → setter target)
 * Note: For bidirectional transfer, we also need a setter in source
 */
class MethodBridgeFormModel extends BridgeFormModel
{
    private string $email = '';

    #[Bridge]
    public function getEmail(): string
    {
        return $this->email;
    }

    #[Bridge]
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function rules(): array
    {
        return [];
    }

    public function getRules(): array
    {
        return $this->rules();
    }
}
