<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Models;

use Blackcube\BridgeModel\Attributes\Bridge;
use Blackcube\BridgeModel\BridgeFormModel;

/**
 * FormModel for testing elastic properties and scenarios with ALL_ELASTIC_ATTRIBUTES / NO_ELASTIC_ATTRIBUTES.
 *
 * Note: This test model uses a workaround since ElasticInterface integration
 * requires a full Swaggest\JsonSchema setup. Instead, we test the elastic
 * behavior by directly manipulating Bridge endpoints with isElastic=true.
 */
class ElasticFormModel extends BridgeFormModel
{
    public const SCENARIO_WITH_ALL_ELASTIC = 'with_all_elastic';
    public const SCENARIO_WITHOUT_ELASTIC = 'without_elastic';
    public const SCENARIO_MIXED = 'mixed';

    #[Bridge]
    public string $name = '';

    #[Bridge]
    public ?string $email = null;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => ['name', 'email', self::ALL_ELASTIC_ATTRIBUTES],
            self::SCENARIO_WITH_ALL_ELASTIC => ['name', self::ALL_ELASTIC_ATTRIBUTES],
            self::SCENARIO_WITHOUT_ELASTIC => ['name', 'email', self::NO_ELASTIC_ATTRIBUTES],
            self::SCENARIO_MIXED => ['name', self::NO_ELASTIC_ATTRIBUTES],
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
