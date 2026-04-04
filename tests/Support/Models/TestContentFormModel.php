<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Models;

use Blackcube\BridgeModel\Attributes\Bridge;
use Blackcube\BridgeModel\BridgeFormModel;

/**
 * FormModel for TestContent AR.
 *
 * Demonstrates DateTimeImmutable ↔ string conversion:
 * - AR has DateTimeImmutable properties
 * - FormModel has string properties with format specified in Bridge attribute
 */
class TestContentFormModel extends BridgeFormModel
{
    public const SCENARIO_CREATE = 'create';
    public const SCENARIO_UPDATE = 'update';

    #[Bridge]
    public string $name = '';

    #[Bridge]
    public ?string $email = null;

    #[Bridge]
    public ?int $age = null;

    #[Bridge]
    public bool $active = false;

    #[Bridge(format: 'Y-m-d')]
    public ?string $birthdate = null;

    #[Bridge(format: 'Y-m-d H:i:s')]
    public ?string $createdAt = null;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => ['name', 'email', 'age', 'active', 'birthdate', 'createdAt'],
            self::SCENARIO_CREATE => ['name', 'email', 'age', 'active', 'birthdate', 'createdAt'],
            self::SCENARIO_UPDATE => ['name', 'email', 'age'],
        ];
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
